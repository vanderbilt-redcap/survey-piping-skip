<?php

namespace Vanderbilt\SurveyPipingSkip;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class SurveyPipingSkip extends AbstractExternalModule
{
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {

    }

    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        $sourceProjects = $this->getProjectSetting('source_project');
        $sourcePartIDs = $this->getProjectSetting('source_part_id');
        $destPartIDs = $this->getProjectSetting('dest_part_id');
        $pipeAll = $this->getProjectSetting('pipe_all_data');
        $sourceForms = $this->getProjectSetting('source_form');
        $destForms = $this->getProjectSetting('dest_form');
        $calcStrings = $this->getProjectSetting('pipe_data_check');

        $destIDField = "";
        $destIDValue = "";
        $destCompleteField = "";
        $destCompleteValue = "";
        $sourceCompleteField = "";
        $sourceCompleteValue = "";

        $currentIndex = "";
        $formIndex = "";
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

        if (!empty($sourceProjects) && $survey_hash != "") {
            $currentProject = new \Project($project_id);
            $currentFormFields = $this->getFieldsOnForm($currentProject->metadata,$instrument);

            $sourceProjectID = $sourceProjects[$currentIndex];

            if ($sourceProjectID != "" && is_numeric($sourceProjectID)) {
                $sourceProject = new \Project($sourceProjectID);
                $sourceForm = $sourceForms[$currentIndex][$subIndex];
                $sourceCompleteField = $sourceForms[$currentIndex][$formIndex]."_complete";
                $sourceFormFields = $this->getFieldsOnForm($sourceProject->metadata,$sourceForm);
                $sourceData = \REDCap::getData($sourceProjectID, 'array', array(), $sourceFormFields, array(), array(), false, false, false, "[".$sourcePartIDs[$currentIndex]."] = '".$destIDValue."'");

                if (!empty($sourceData)) {
                    $transferData = array();
                    foreach ($sourceData as $recordID => $currentData) {
                        foreach ($currentData as $eventID => $eventData) {
                            if ($eventID == "repeat_instances") {
                                foreach ($eventData as $subEventID => $subEventData) {
                                    foreach ($subEventData as $subInstrument => $subInstrumentData) {
                                        if ($calcStrings[$currentIndex] != "" && $this->getCalculatedData($calcStrings[$currentIndex],$sourceData,$subEventID,$sourceProjectID,$sourceForm,$repeat_instance) == "1") continue;
                                        foreach ($subInstrumentData[$repeat_instance] as $fieldName => $subFieldData) {
                                            if (!in_array($fieldName,$sourceFormFields)) continue;
                                            if ($fieldName == $sourceCompleteField) {
                                                //$transferData[$record][$event_id]['repeat_instances'][$event_id][$instrument][$repeat_instance][$destCompleteField] = $subFieldData;
                                            }
                                            elseif ($pipeAll[$currentIndex] == "yes" && in_array($fieldName,$currentFormFields) && $currentProject->metadata[$fieldName]['element_type'] == $sourceProject->metadata[$fieldName]['element_type'] && $currentProject->metadata[$fieldName]['element_enum'] == $sourceProject->metadata[$fieldName]['element_enum']) {
                                                $transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$fieldName] = $subFieldData;
                                            }
                                        }
                                    }
                                }
                            } else {
                                if ($calcStrings[$currentIndex] != "" && $this->getCalculatedData($calcStrings[$currentIndex],$sourceData,$eventID,$sourceProjectID,$sourceForm,$repeat_instance) != "1") continue;

                                foreach ($eventData as $fieldName => $fieldData) {
                                    if (!in_array($fieldName,$sourceFormFields)) continue;
                                    if ($fieldName == $sourceCompleteField) {
                                        //$transferData[$record][$event_id][$destCompleteField] = $fieldData;
                                    }
                                    elseif ($pipeAll[$currentIndex] == "yes" && in_array($fieldName,$currentFormFields) && $currentProject->metadata[$fieldName]['element_type'] == $sourceProject->metadata[$fieldName]['element_type'] && $currentProject->metadata[$fieldName]['element_enum'] == $sourceProject->metadata[$fieldName]['element_enum']) {
                                        $transferData[$record][$event_id][$fieldName] = $fieldData;
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($transferData)) {
                        if ($currentProject->isRepeatingFormOrEvent($event_id,$instrument)) {
                            /*$transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance]['redcap_repeat_instance'] = $repeat_instance;
                            $transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance]['redcap_repeat_instrument'] = $destForms[$currentIndex][$subIndex];*/
                            $transferData[$record][$event_id]['redcap_repeat_instance'] = $repeat_instance;
                            $transferData[$record][$event_id]['redcap_repeat_instrument'] = $destForms[$currentIndex][$subIndex];
                        }
                        /*echo "<pre>";
                        print_r($transferData);
                        echo "</pre>";*/
                        $saveResult = \REDCap::saveData($project_id,'array',$transferData);
                        /*echo "<pre>";
                        print_r($saveResult);
                        echo "</pre>";*/
                        //exit;
                        /*echo "$(document).ready(function() {
                            formSubmitDataEntry();
                        });";*/
                    }
                }
            }
        }
    }

    function redcap_survey_page_top($project_id,$record,$instrument,$event_id,$group_id,$survey_hash,$response_id,$repeat_instance = 1) {
        $sourceProjects = $this->getProjectSetting('source_project');
        $sourcePartIDs = $this->getProjectSetting('source_part_id');
        $destPartIDs = $this->getProjectSetting('dest_part_id');
        $pipeAll = $this->getProjectSetting('pipe_all_data');
        $sourceForms = $this->getProjectSetting('source_form');
        $destForms = $this->getProjectSetting('dest_form');
        $calcStrings = $this->getProjectSetting('pipe_data_check');

        $destIDField = "";
        $destIDValue = "";
        $destCompleteField = "";
        $destCompleteValue = "";
        $sourceCompleteField = "";
        $sourceCompleteValue = "";

        $currentIndex = "";
        $formIndex = "";
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

        if (!empty($sourceProjects) && $destCompleteValue != "2") {
            $currentProject = new \Project($project_id);
            $currentFormFields = $this->getFieldsOnForm($currentProject->metadata,$instrument);

            $sourceProjectID = $sourceProjects[$currentIndex];
            if ($sourceProjectID != "" && is_numeric($sourceProjectID)) {
                $sourceProject = new \Project($sourceProjectID);
                $sourceForm = $sourceForms[$currentIndex][$subIndex];
                $sourceCompleteField = $sourceForm."_complete";
                $sourceFormFields = $this->getFieldsOnForm($sourceProject->metadata,$sourceForm);
                if (!in_array($sourcePartIDs[$currentIndex],$sourceFormFields)) {
                    $sourceFormFields = array($sourcePartIDs[$currentIndex]);
                }

                $sourceData = \REDCap::getData($sourceProjectID, 'array', array(), array(), array(), array(), false, false, false, "[".$sourcePartIDs[$currentIndex]."] = '".$destIDValue."'");

                if (!empty($sourceData)) {
                    $sourceCompleteValue = "";
                    foreach ($sourceData as $recordID => $currentData) {
                        foreach ($currentData as $eventID => $eventData) {
                            if ($eventID == "repeat_instances") {
                                foreach ($eventData as $subEventID => $subEventData) {
                                    foreach ($subEventData as $subInstrument => $subInstrumentData) {
                                        if ($calcStrings[$currentIndex] != "" && $this->getCalculatedData($calcStrings[$currentIndex],$sourceData,$subEventID,$sourceProjectID,$sourceForm,$repeat_instance) != "1") continue;
                                        foreach ($subInstrumentData[$repeat_instance] as $fieldName => $subFieldData) {
                                            if ($fieldName == $sourceCompleteField) {
                                                $sourceCompleteValue = $subFieldData;
                                                //$transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$destCompleteField] = $sourceCompleteValue;
                                            }
                                        }
                                    }
                                }
                            } else {
                                if ($calcStrings[$currentIndex] != "" && $this->getCalculatedData($calcStrings[$currentIndex],$sourceData,$eventID,$sourceProjectID,$sourceForm,$repeat_instance) != "1") continue;
                                foreach ($eventData as $fieldName => $fieldData) {
                                    if ($fieldName == $sourceCompleteField) {
                                        $sourceCompleteValue = $fieldData;
                                        //$transferData[$record][$event_id][$destCompleteField] = $sourceCompleteValue;
                                    }
                                }
                            }
                        }
                    }

                    /*if (!empty($transferData) && $currentProject->isRepeatingFormOrEvent($event_id,$instrument)) {
                        $transferData[$record][$event_id]['repeat_instances'][$event_id][$instrument][$repeat_instance]['redcap_repeat_instance'] = $repeat_instance;
                        $transferData[$record][$event_id]['repeat_instances'][$event_id][$instrument][$repeat_instance]['redcap_repeat_instrument'] = $destForms[$currentIndex][$subIndex];
                        $transferData[$record][$event_id]['redcap_repeat_instance'] = $repeat_instance;
                        $transferData[$record][$event_id]['redcap_repeat_instrument'] = $destForms[$currentIndex][$subIndex];
                    }*/

                    if ($sourceCompleteValue == "2") {
                        /*$saveResult = \REDCap::saveData($project_id,'array',$transferData);
                        echo "<pre>";
                        print_r($saveResult);
                        echo "</pre>";*/
                        echo "<script>
                            $(document).ready(function() {
                                formSubmitDataEntry();
                            });
                        </script>";
                    }
                }
            }
        }
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
}