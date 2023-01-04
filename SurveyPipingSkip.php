<?php

namespace Vanderbilt\SurveyPipingSkip;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class SurveyPipingSkip extends AbstractExternalModule
{
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        //list($transferData,$currentIndex,$formIndex) = $this->getMatchingRecordData("submit",$project_id,$record,$instrument,$event_id,$group_id,null,null,$repeat_instance);
        $validForms = $this->getProjectSetting("dest_form",$project_id);
        $showOnDE = $this->getProjectSetting("show_data_entry",$project_id);
        if (is_array($validForms)) {
            foreach ($validForms as $topIndex => $subSetting) {
                foreach ($subSetting as $index => $vForm) {
                    if (is_array($showOnDE) && $vForm == $instrument && $showOnDE[$topIndex][$index] == "yes") {
                        $this->processFormFields("form", $project_id, $record, $instrument, $event_id, $group_id, $repeat_instance);
                        break;
                    }
                }
            }
        }
    }

    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        list($transferData,$currentIndex,$formIndex) = $this->getMatchingRecordData("submit",$project_id,$record,$instrument,$event_id,$group_id,$survey_hash,$repeat_instance);
        if (!empty($transferData)) {
            /*echo "<pre>";
            print_r($transferData);
            echo "</pre>";*/
            $saveResult = \REDCap::saveData($project_id, 'array', $transferData);
            /*echo "<pre>";
            print_r($saveResult);
            echo "</pre>";*/
            //exit;
            /*echo "$(document).ready(function() {
                formSubmitDataEntry();
            });";*/
        }
    }

    function redcap_survey_page_top($project_id,$record,$instrument,$event_id,$group_id,$survey_hash,$response_id,$repeat_instance = 1)
    {
        $validForms = $this->getProjectSetting("dest_form",$project_id);

        if (is_array($validForms)) {
            foreach ($validForms as $topIndex => $subSetting) {
                foreach ($subSetting as $index => $vForm) {
                    if ($vForm == $instrument) {
                        $this->processFormFields("survey", $project_id, $record, $instrument, $event_id, $group_id, $repeat_instance, $survey_hash);
                        break;
                    }
                }
            }
        }
        //$this->processFormFields("survey",$project_id,$record,$instrument,$event_id,$group_id,$repeat_instance,$survey_hash);
    }

    function processFormFields($view_type,$project_id,$record,$instrument,$event_id,$group_id,$repeat_instance,$survey_hash = "")
    {
        $sess_id_1 = session_id();
        $sess_id_2 = "survey-module";
        session_write_close();
        session_id($sess_id_2);
        session_start();

        if (empty($_SESSION['survey_piping_token'])) {
            $_SESSION['survey_piping_token'] = bin2hex(random_bytes(32));
        }
        $token = $_SESSION['survey_piping_token'];

        $destPartIDs = $this->getProjectSetting('dest_part_id');
        $autoSubmit = $this->getProjectSetting('auto_submit');

        list($sourceData, $currentIndex, $formIndex) = $this->getMatchingRecordData("submit", $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $repeat_instance);

        if ($autoSubmit[$currentIndex][$formIndex] == "yes" && !empty($sourceData)) {
            if (trim($_GET['__reqmsg']) == '') {
                echo "<script>
                    $(document).ready(function() {
                        formSubmitDataEntry();
                    });
                </script>";
            }
        } else {
            echo "<script>
                var lastFieldData = {};
                function surveyPipingData(triggerfield) {
                        var value = triggerfield.val();
                        var name = triggerfield.prop('name');
                        console.log(name);
                        console.log(value);
                        $.ajax({
                            url: '" . $this->getUrl('ajax_data.php') . "&NOAUTH',
                            method: 'post',
                            data: {
                                'return_type': 'data', 
                                'project_id': '" . $project_id . "', 
                                'record': '" . $record . "',
                                'instrument': '" . $instrument . "', 
                                'event_id': '" . $event_id . "',
                                'group_id': '" . $group_id . "',
                                'survey_hash': '" . $survey_hash . "',
                                'repeat_instance': '" . $repeat_instance . "',
                                'token': '" . $token . "',
                                'check_value': value
                            },
                            success: function (data) {
                                console.log(data);
                                var dataArray = JSON.parse(data);
                                //console.log(dataArray);
                                var metadata = dataArray['field_types'];
                                //console.log(metadata);
                                var fielddata = dataArray['data'];
                                console.log('Field Data');
                                console.log(fielddata);
                                console.log(lastFieldData);
                                
                                if (fielddata !== null && !objectsEqual(fielddata,lastFieldData)) {
                                    for (fname in metadata) {
                                        console.log('Field name: '+fname);
                                        if (fname == name) continue;
                                        
                                        var datapoint = '';
                                        //console.log('Field data');
                                        //console.log(fielddata);
                                        if (fname in fielddata) {
                                            datapoint = fielddata[fname];
                                            switch(metadata[fname]) {
                                                case 'text':
                                                    $('input[name=\"'+fname+'\"]').val(datapoint).change();
                                                    break;
                                                case 'textarea':
                                                    $('textarea[name=\"'+fname+'\"]').val(datapoint).change();
                                                    break;
                                                case 'radio':
                                                case 'yesno':
                                                case 'truefalse':
                                                    $('input[name=\"'+fname+'___radio\"][value=\"'+datapoint+'\"]').click();
                                                    break;
                                                case 'checkbox':
                                                    for (data in datapoint) {
                                                        $('#id-__chk__'+fname+'_RC_'+datapoint[data]).click();
                                                    }
                                                    break;
                                                case 'select':
                                                    $('select[name=\"'+fname+'\"]').val(datapoint).change();
                                                    break;
                                                default:
                                                    break;
                                            }
                                        }
                                    }
                                }
                                else {
                                    //console.log('Stopped for duplicate');
                                }
                                lastFieldData = fielddata;
                                //$('#'+destination).css('display','inline-block').html(data);
                                //$('#accordion > div').accordion({ header: 'h3', collapsible: true, active: false });
                            },
                            error: function (errorThrown) {
                                console.log(errorThrown);
                            }
                        });
                    }
                    $(document).ready(function() {    
                        $('[name=\"" . $destPartIDs[$currentIndex] . "\"]').change(function() {
                            //console.log($(this).val());
                            surveyPipingData($(this));
                        });
                    });
                    function arraysEqual(a, b) {
                        console.log('Comparing arrays');
                        console.log(a);
                        console.log(b);
                      return Array.isArray(a) &&
                            Array.isArray(b) &&
                            a.length === b.length &&
                            a.every((val, index) => val === b[index]);
                    }
                    function objectsEqual(a, b) {
                        if (a === null && b === null) return true;
                        if (a === null || b === null) return false;
                        
                        // Create arrays of property names
                        var aProps = Object.getOwnPropertyNames(a);
                        var bProps = Object.getOwnPropertyNames(b);
                    
                        // If number of properties is different,
                        // objects are not equivalent
                        if (aProps.length != bProps.length) {
                            return false;
                        }
                    
                        for (var i = 0; i < aProps.length; i++) {
                            var propName = aProps[i];
                    
                            // If values of same property are not equal,
                            // objects are not equivalent
                            if (a[propName] !== b[propName]) {
                                return false;
                            }
                        }
                    
                        // If we made it this far, objects
                        // are considered equivalent
                        return true;
                    }
                </script>";
        }
        session_write_close();
        session_id($sess_id_1);
        session_start();
    }

    function getCalculatedData($calcString,$recordData,$event_id,$project_id,$repeat_instrument,$repeat_instance=null) {
        $formatCalc = \Calculate::formatCalcToPHP($calcString);
        //echo "The string!!<br/>$formatCalc<br/>";
        $parser = new \LogicParser();
        try {
            list($funcName, $argMap) = $parser->parse($formatCalc, $event_id, true, false);
            $thisInstanceArgMap = $argMap;
            $Proj = new \Project($project_id);
            foreach ($thisInstanceArgMap as &$theseArgs) {
                $theseArgs[0] = $event_id;
            }
            //echo "Form: ".$Proj->metadata['age']['form_name']."<br/>";

            if ($repeat_instance != "") {
                foreach ($thisInstanceArgMap as &$theseArgs) {
                    // If there is no instance number for this arm map field, then proceed
                    if ($theseArgs[3] == "") {
                        $thisInstanceArgEventId = ($theseArgs[0] == "") ? $event_id : $theseArgs[0];
                        $thisInstanceArgEventId = is_numeric($thisInstanceArgEventId) ? $thisInstanceArgEventId : $Proj->getEventIdUsingUniqueEventName($thisInstanceArgEventId);
                        $thisInstanceArgField = $theseArgs[1];
                        $thisInstanceArgFieldForm = $Proj->metadata[$thisInstanceArgField]['form_name'];
                        // If this event or form/event is repeating event/instrument, the add the current instance number to arg map
                        if ( // Is a valid repeating instrument?
                            ($repeat_instrument != '' && $thisInstanceArgFieldForm == $repeat_instrument && $Proj->isRepeatingForm($thisInstanceArgEventId, $thisInstanceArgFieldForm))
                            // Is a valid repeating event?
                            || ($repeat_instrument == '' && $Proj->isRepeatingEvent($thisInstanceArgEventId) && in_array($thisInstanceArgFieldForm, $Proj->eventsForms[$thisInstanceArgEventId]))) {
                            $theseArgs[3] = $repeat_instance;
                        }
                    }
                }
                unset($theseArgs);
            }
            /*echo "<pre>";
            print_r($thisInstanceArgMap);
            echo "</pre>";*/
            foreach ($recordData as $record => &$this_record_data1) {
                $calculatedCalcVal = \LogicTester::evaluateCondition(null, $this_record_data1, $funcName, $thisInstanceArgMap, null);
                //echo "The calc value: $calculatedCalcVal<br/>";
                foreach (parseEnum(strip_tags(label_decode($Proj->metadata[$thisInstanceArgMap[count($thisInstanceArgMap) - 1][1]]['element_enum']))) as $this_code => $this_choice) {
                    if ($calculatedCalcVal === $this_code) {
                        $calculatedCalcVal = $this_choice;
                        break;
                    }
                }
            }
        }
        catch (\Exception $e) {
            if (strpos($e->getMessage(),"Parse error in input:") === 0 || strpos($e->getMessage(),"Unable to find next token in") === 0) {
                return $calcString;
            }
            else {
                return "";
            }
        }
        return $calculatedCalcVal;
    }

    function getFieldsOnForm($metadata, $formname) {
        $fieldList = array();

        foreach ($metadata as $fieldName => $fieldInfo) {
            if ($fieldInfo['form_name'] == $formname) {
                $fieldList[] = $fieldName;
            }
        }
        return $fieldList;
    }

    function getMatchingRecordData($returnType, $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $repeat_instance, $chosenValue = "") {
        $sourceProjects = $this->getProjectSetting('source_project',$project_id);
        $sourcePartIDs = $this->getProjectSetting('source_part_id',$project_id);
        $destPartIDs = $this->getProjectSetting('dest_part_id',$project_id);
        $pipeAll = $this->getProjectSetting('pipe_all_data',$project_id);
        $pipeFields = $this->getProjectSetting('pipe_fields',$project_id);
        $dataChecks = $this->getProjectSetting('show_data_entry',$project_id);
        $sourceForms = $this->getProjectSetting('source_form',$project_id);
        $destForms = $this->getProjectSetting('dest_form',$project_id);
        $calcStrings = $this->getProjectSetting('pipe_data_check',$project_id);

        $destIDField = "";
        $destIDValue = "";
        $destCompleteField = "";
        $destCompleteValue = "";
        $sourceCompleteField = "";
        $sourceCompleteValue = "";

        $currentIndex = "";
        $formIndex = "";

        $assert = function($calcString,$sourceData,$eventID,$projectID,$form,$instance) {
            if ($calcString == "" || $this->getCalculatedData($calcString,$sourceData,$eventID,$projectID,$form,$instance) == "1")
                return true;
            return false;
        };

        $transferData = array();

        foreach ($destForms as $index => $formList) {
            if (!is_numeric($currentIndex)) {
                foreach ($formList as $subIndex => $formName) {
                    if ($formName == $instrument) {
                        $destIDField = $destPartIDs[$index];
                        $destCompleteField = $formName . "_complete";
                        $currentIndex = $index;
                        $formIndex = $subIndex;
                    }
                }
            }
        }

        $currentData = \REDCap::getData($project_id,'array',array($record),array($destIDField,$destCompleteField));

        foreach ($currentData[$record] as $eventID => $eventData) {
            if ($eventID == "repeat_instances") {
                foreach ($eventData as $subEventID => $subEventData) {
                    foreach ($subEventData as $instrument => $subInstrumentData) {
                        foreach ($subInstrumentData[$repeat_instance] as $fieldName => $subFieldData) {
                            if ($fieldName == $destIDField) {
                                $destIDValue = $subFieldData;
                            }
                            elseif ($fieldName == $destCompleteField) {
                                $destCompleteValue = $subFieldData;
                            }
                        }
                    }
                }
            }
            else {
                foreach ($eventData as $fieldName => $fieldData) {
                    if ($fieldName == $destIDField) {
                        $destIDValue = $fieldData;
                    }
                    elseif ($fieldName == $destCompleteField) {
                        $destCompleteValue = $fieldData;
                    }
                }
            }
        }
        if ($chosenValue != "") {
            $destIDValue = $chosenValue;
        }

        if (!empty($sourceProjects) && ($survey_hash != "" || $dataChecks[$currentIndex][$formIndex] == "yes")) {
            $currentProject = new \Project($project_id);
            $instrumentRepeats = $currentProject->isRepeatingFormOrEvent($event_id,$instrument);
            $currentFormFields = $this->getFieldsOnForm($currentProject->metadata,$instrument);

            $sourceProjectID = $sourceProjects[$currentIndex];

            if ($sourceProjectID != "" && is_numeric($sourceProjectID)) {
                $sourceProject = new \Project($sourceProjectID);
                $sourceForm = $sourceForms[$currentIndex][$formIndex];
                $sourceCompleteField = $sourceForm."_complete";
                $sourceFormFields = $this->getFieldsOnForm($sourceProject->metadata,$sourceForm);
                $sourceData = \REDCap::getData($sourceProjectID, 'array', array(), $sourceFormFields, array(), array(), false, false, false, "[".$sourcePartIDs[$currentIndex]."] = '".$destIDValue."'");

                if (!empty($sourceData)) {
                    foreach ($sourceData as $recordID => $currentData) {
                        foreach ($currentData as $eventID => $eventData) {
                            if ($eventID == "repeat_instances") {
                                foreach ($eventData as $subEventID => $subEventData) {
                                    foreach ($subEventData as $subInstrument => $subInstrumentData) {
                                        if (!$assert($calcStrings[$currentIndex][$formIndex],$sourceData,$subEventID,$sourceProjectID,$sourceForm,$repeat_instance)) continue;
                                        //if ($calcStrings[$currentIndex] != "" && $this->getCalculatedData($calcStrings[$currentIndex],$sourceData,$subEventID,$sourceProjectID,$sourceForm,$repeat_instance) == "1") continue;

                                        foreach ($subInstrumentData[$repeat_instance] as $fieldName => $subFieldData) {
                                            if (!in_array($fieldName,$sourceFormFields)) continue;
                                            if ($returnType == 'data' && $this->getDateFormat($currentProject->metadata[$fieldName]['element_validation_type'],'','php') != "") {
                                                $subFieldData = date($this->getDateFormat($currentProject->metadata[$fieldName]['element_validation_type'],'','php'),strtotime($subFieldData));
                                                if (!$this->validateDate($fieldData,$this->getDateFormat($currentProject->metadata[$fieldName]['element_validation_type'],'','php'))) {
                                                    $subFieldData = "";
                                                }
                                            }

                                            if ($fieldName == $sourceCompleteField && $returnType == "submit") {
                                                $this->setTransferData($transferData,$instrumentRepeats,$record,$event_id,$instrument,$fieldName,$subFieldData,$repeat_instance);
                                                //$transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$fieldName] = $subFieldData;
                                            }
                                            elseif (($pipeAll[$currentIndex] == "yes" || ($pipeAll[$currentIndex] == "no" && in_array($fieldName,$pipeFields[$currentIndex][$formIndex]))) && in_array($fieldName,$currentFormFields) && $currentProject->metadata[$fieldName]['element_type'] == $sourceProject->metadata[$fieldName]['element_type'] && $currentProject->metadata[$fieldName]['element_enum'] == $sourceProject->metadata[$fieldName]['element_enum']) {
                                                $this->setTransferData($transferData,$instrumentRepeats,$record,$event_id,$instrument,$fieldName,$subFieldData,$repeat_instance);
                                                //$transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$fieldName] = $subFieldData;
                                            }
                                        }
                                    }
                                }
                            } else {
                                if (!$assert($calcStrings[$currentIndex][$formIndex],$sourceData,$eventID,$sourceProjectID,$sourceForm,$repeat_instance)) continue;
                                //if ($calcStrings[$currentIndex] != "" && $this->getCalculatedData($calcStrings[$currentIndex],$sourceData,$eventID,$sourceProjectID,$sourceForm,$repeat_instance) != "1") continue;

                                foreach ($eventData as $fieldName => $fieldData) {
                                    if (!in_array($fieldName,$sourceFormFields)) continue;
                                    if ($returnType == 'data' && $this->getDateFormat($currentProject->metadata[$fieldName]['element_validation_type'],'','php') != "") {
                                        $fieldData = date($this->getDateFormat($currentProject->metadata[$fieldName]['element_validation_type'],'','php'),strtotime($fieldData));
                                        if (!$this->validateDate($fieldData,$this->getDateFormat($currentProject->metadata[$fieldName]['element_validation_type'],'','php'))) {
                                            $fieldData = "";
                                        }
                                    }
                                    if ($fieldName == $sourceCompleteField && $returnType == "submit") {
                                        $this->setTransferData($transferData,$instrumentRepeats,$record,$event_id,$instrument,$fieldName,$fieldData,$repeat_instance);
                                        //$transferData[$record][$event_id][$fieldName] = $fieldData;
                                    }
                                    elseif (($pipeAll[$currentIndex] == "yes" || ($pipeAll[$currentIndex] == "no" && in_array($fieldName,$pipeFields[$currentIndex][$formIndex]))) && in_array($fieldName,$currentFormFields) && $currentProject->metadata[$fieldName]['element_type'] == $sourceProject->metadata[$fieldName]['element_type'] && $currentProject->metadata[$fieldName]['element_enum'] == $sourceProject->metadata[$fieldName]['element_enum']) {
                                        $this->setTransferData($transferData,$instrumentRepeats,$record,$event_id,$instrument,$fieldName,$fieldData,$repeat_instance);
                                        //$transferData[$record][$event_id][$fieldName] = $fieldData;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($instrumentRepeats) {
                    $transferData[$record][$event_id]['redcap_repeat_instance'] = $repeat_instance;
                    $transferData[$record][$event_id]['redcap_repeat_instrument'] = $destForms[$currentIndex][$formIndex];
                }
            }
        }

        return array($transferData,$currentIndex,$formIndex);
    }

    function setTransferData(&$transferData, $isRepeating, $record, $event_id, $instrument, $fieldName, $fieldData, $repeat_instance = 1) {
        if ($isRepeating) {
            $transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$fieldName] = $fieldData;
        }
        else {
            $transferData[$record][$event_id][$fieldName] = $fieldData;
        }
    }

    function findQuestionBySection($project_id,$instrument) {
        $result = $this->query("SELECT question_by_section FROM redcap_surveys WHERE project_id=? AND form_name=?",[$project_id,$instrument]);
        return $result->fetch_assoc()['question_by_section'];
    }

    function generatePrefillJavascript(\MetaData $projectMetadata, $triggerField, $fieldsToFill, $recordData) {
        $javaString = "";

        return $javaString;
    }

    /*
	 * Determine the correct date formatting based on a field's element validation.
	 * @param $elementValidationType The element validation for the data field being examined.
	 * @param $type Either 'php' or 'javascript', based on where the data format string is being injected
	 * @return Date format string
	 */
    function getDateFormat($elementValidationType, $fieldName, $type) {
        $returnString = "";
        switch ($elementValidationType) {
            case "date_mdy":
                if ($type == "php") {
                    $returnString = "m-d-Y";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+'-'+$fieldName.getUTCFullYear()";
                }
                break;
            case "date_dmy":
                if ($type == "php") {
                    $returnString = "d-m-Y";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCDate())+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+$fieldName.getUTCFullYear()";
                }
                break;
            case "date_ymd":
                if ($type == "php") {
                    $returnString = "Y-m-d";
                }
                elseif ($type == "javascript") {
                    $returnString = "$fieldName.getUTCFullYear()+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())";
                }
                break;
            case "datetime_mdy":
                if ($type == "php") {
                    $returnString = "m-d-Y H:i";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())";
                }
                break;
            case "datetime_dmy":
                if ($type == "php") {
                    $returnString = "d-m-Y H:i";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCDate())+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())";
                }
                break;
            case "datetime_ymd":
                if ($type == "php") {
                    $returnString = "Y-m-d H:i";
                }
                elseif ($type == "javascript") {
                    $returnString = "$fieldName.getUTCFullYear()+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())";
                }
                break;
            case "datetime_seconds_mdy":
                if ($type == "php") {
                    $returnString = "m-d-Y H:i:s";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())+':'+addZ($fieldName.getUTCSeconds())";
                }
                break;
            case "datetime_seconds_dmy":
                if ($type == "php") {
                    $returnString = "d-m-Y H:i:s";
                }
                elseif ($type == "javascript") {
                    $returnString = "addZ($fieldName.getUTCDate())+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+$fieldName.getUTCFullYear()+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())+':'+addZ($fieldName.getUTCSeconds())";
                }
                break;
            case "datetime_seconds_ymd":
                if ($type == "php") {
                    $returnString = "Y-m-d H:i:s";
                }
                elseif ($type == "javascript") {
                    $returnString = "$fieldName.getUTCFullYear()+'-'+addZ($fieldName.getUTCMonth()+1)+'-'+addZ($fieldName.getUTCDate())+' '+addZ($fieldName.getUTCHours())+':'+addZ($fieldName.getUTCMinutes())+':'+addZ($fieldName.getUTCSeconds())";
                }
                break;
            default:
                $returnString = '';
        }
        return $returnString;
    }

    function validateDate($date,$format='Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }
}