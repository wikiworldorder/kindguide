<?php
namespace KindGuide\Controllers;

use DB;
use Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\KGNonprofit;
use KindGuide\Controllers\KindAudits;

class KindGuide extends KindAudits
{
    // Initializing a bunch of things which are not [yet] automatically determined by the software
    protected function initExtra(Request $request)
    {
        // Establishing Main Navigation Organization, with Node ID# and Section Titles
        $this->majorSections = [];
        return true;
    }
        
    // Initializing a bunch of things which are not [yet] automatically determined by the software
    protected function loadExtra()
    {
        return true;
    }
    
    protected function isPublic()
    {
        if ($this->treeID == 24) { // Nonprofit
            return (isset($this->sessData->dataSets["Nonprofit"]) 
                && isset($this->sessData->dataSets["Nonprofit"][0]->NonName) 
                && trim($this->sessData->dataSets["Nonprofit"][0]->NonName) != ''
                && $GLOBALS["SL"]->def->getID('Nonprofit Status', 'Published'));
        }
        return false;
    }
    
    public function isPublished($coreTbl, $coreID, $coreRec = NULL)
    {
//echo '<br /><br /><br />isPublished(' . $coreTbl . ', ' . $coreID . ', <pre>'; print_r($coreRec); echo '</pre>';
        if ($coreTbl == 'Nonprofit') {
            if (!$coreRec) {
                $coreRec = KGNonprofit::find($coreID);
            }
            if ($coreRec && isset($coreRec->NonName) && trim($coreRec->NonName) != '') {
                return true;
            }
        }
        return false;
    }
    
    protected function recordIsEditable($coreTbl, $coreID, $coreRec = NULL)
    {
        if ($this->v["isAdmin"]) {
            return true;
        }
        if ($coreTbl == 'Nonprofit') {
            if (!$coreRec && $coreID > 0) {
                $coreRec = KGNonprofit::find($coreID);
            }
            if (!isset($coreRec->NonStatus)) {
                return true;
            }
            if (!$coreRec) {
                return false;
            }
            if ($coreRec->NonStatus == $GLOBALS["SL"]->def->getID('Nonprofit Status', 'Incomplete')) {
                return true;
            }
        }
        return false;
    }
    
    public function getAllPublicCoreIDs($coreTbl = '')
    {
        if (trim($coreTbl) == '') $coreTbl = $GLOBALS["SL"]->coreTbl;
        $this->allPublicCoreIDs = [];
        if ($coreTbl == 'Nonprofit') {
            $list = KGNonprofit::whereNotNull('NonName')
                ->where('NonName', 'NOT LIKE', '')
                ->select('NonID')
                ->get();
            if ($list->isNotEmpty()) {
                foreach ($list as $l) $this->allPublicCoreIDs[] = $l->NonID;
            }
        }
        return $this->allPublicCoreIDs;
    }
    
    protected function customNodePrint($nID = -3, $tmpSubTier = [], $nIDtxt = '', $nSffx = '', $currVisib = 1)
    {
        $ret = '';
        if ($nID == 262) {
            if (isset($this->v["tokenUser"]) && $this->v["tokenUser"]) {
                $ret = $this->processTokenAccess();
            } else {
                $ret = '<div class="w100" style="padding: 20px;"><center>' 
                    . $this->getMfaForm('(sent to you by email)', 'Start Survey') . '</center></div>';
            }
        } elseif ($nID == 129) {
            if (isset($this->sessData->dataSets["Business"]) && sizeof($this->sessData->dataSets["Business"]) > 0
                && isset($this->sessData->dataSets["Business"][0]->BusStatus)) {
                $this->sessData->dataSets["Business"][0]->BusStatus 
                    = $GLOBALS["SL"]->def->getID('Business Survey Status', 'Complete');
                $this->sessData->dataSets["Business"][0]->save();
            }
        } elseif ($nID == 348) {
            return $this->printConsumerSurveySocialLinks();
        } elseif ($nID == 372) {
            return $this->printNonprofitSocialLinks(). '<p>&nbsp;</p>';
        } elseif ($nID == 349) {
//            if (isset($this->sessData->dataSets["BusinessInvites"]) && !isset($this->sessData->dataSets["BusinessInvites"][0]->
            
        // Manage Audits
        } elseif ($nID == 495) {
            return $this->printCertSkinStyles($nID);
        } elseif ($nID == 507) {
            return $this->printAuditOverviewOpts($nID);
        } elseif (in_array($nID, [493, 505])) {
            return $this->printAuditOverview($nID);
        } elseif ($nID == 486) {
            return $this->printAuditImport($nID);
        }
        return $ret;
    }
    
    public function printPreviewReportCustom($isAdmin = false)
    {
        if (!isset($this->sessData->dataSets[$GLOBALS["SL"]->coreTbl])) {
            return '';
        }
        if ($GLOBALS["SL"]->coreTbl == 'Nonprofit') {
            return view('vendor.kindguide.nonprofit-report-preview', [
                "uID"         => $this->v["uID"],
                "isAdmin"     => $this->v["isAdmin"],
                "nonprofit"   => $this->sessData->dataSets["Nonprofit"][0]
            ])->render();
        }
        return '';
    }
    
    public function printFullReport($reportType = '', $isAdmin = false, $inForms = false)
    {
        
        
        return '';
    }
    
    public function ajaxChecksCustom(Request $request, $type = '')
    {
        if ($type == 'send-bus-token') {
            return $this->ajaxSendBusTok($request);
        }
        return '';
    }
    
    public function sendEmailBlurbsCustom($emailBody, $deptID = -3)
    {   
        $dynamos = [
            '[{ Business ID }]', 
            '[{ Business URL }]', 
            '[{ Business URL Link }]', 
            '[{ Days From Now: 7, mm/dd/yyyy }]', 
            '[{ Complaint Number of Weeks Old }]', 
            '[{ Oversight Complaint Token URL Link }]', 
            '[{ Oversight Complaint Secure MFA }]'
        ];
        
        foreach ($dynamos as $dy) {
            if (strpos($emailBody, $dy) !== false) {
                $swap = $dy;
                $dyCore = str_replace('[{ ', '', str_replace(' }]', '', $dy));
                switch ($dy) {
                    case '[{ Business ID }]': 
                        $swap = $this->corePublicID;
                        break;
                    case '[{ Business URL }]':
                        $swap = $GLOBALS["SL"]->swapURLwrap($GLOBALS["SL"]->sysOpts["app-url"] . '/complaint/read-' 
                            . $this->corePublicID);
                        break;
                    case '[{ Business URL Link }]':
                        $swap = $GLOBALS["SL"]->sysOpts["app-url"] . '/complaint/read-' . $this->corePublicID;
                        break;
                    case '[{ Days From Now: 7, mm/dd/yyyy }]':
                        $swap = date('n/j/y', mktime(0, 0, 0, date("n"), (7+date("j")), date("Y")));
                        break;
                    case '[{ Business Token URL Link }]':
                        $token = $this->createToken('Sensitive', $this->treeID, $this->coreID, $deptUser->id);
                        $swap = $GLOBALS["SL"]->sysOpts["app-url"] . '/kind-business-survey?b='
                            . $this->corePublicID . '&t=' . $token;
                        $swap = '<a href="' . $swap . '" target="_blank">' . $swap . '</a>';
                        break;
                    case '[{ Business Secure MFA }]':
                        $deptUser = $this->getDeptUser($deptID);
                        if (!isset($deptUser->id)) {
                            $swap = '<span style="color: red;">* DEPARTMENT IS NOT OPC-COMPLIANT *</span>';
                        } else {
                            $swap = $this->createToken('MFA', $this->treeID, $this->coreID, $deptUser->id);
                        }
                        break;
                }
                $emailBody = str_replace($dy, $swap, $emailBody);
            }
        }
        return $emailBody;
    }
    
}
