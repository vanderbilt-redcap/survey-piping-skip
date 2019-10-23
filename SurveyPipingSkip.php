<?php

namespace Vanderbilt\SurveyPipingSkip;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class SurveyPipingSkip extends AbstractExternalModule
{
    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        $sourceProjects = $this->getProjectSetting('source_project');
        $sourcePartIDs = $this->getProjectSetting('source_part_id');
        $destPartIDs = $this->getProjectSetting('dest_part_id');
        $pipeAll = $this->getProjectSetting('pipe_all_data');
        $sourceForms = $this->getProjectSetting('source_form');
        $destForms = $this->getProjectSetting('dest_form');

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
                $sourceCompleteField = $sourceForms[$currentIndex][$formIndex]."_complete";
                $sourceFormFields = $this->getFieldsOnForm($sourceProject->metadata,$sourceForm);
                $sourceData = \REDCap::getData($sourceProjectID, 'array', array(), array($sourceFormFields), array(), array(), false, false, false, "[".$sourcePartIDs[$currentIndex]."] = '".$destIDValue."'");

                if (!empty($sourceData)) {
                    $transferData = array();
                    foreach ($sourceData as $recordID => $currentData) {
                        foreach ($currentData as $eventID => $eventData) {
                            if ($eventID == "repeat_instances") {
                                foreach ($eventData as $subEventID => $subEventData) {
                                    foreach ($subEventData as $subInstrument => $subInstrumentData) {
                                        foreach ($subInstrumentData[$repeat_instance] as $fieldName => $subFieldData) {
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
                                foreach ($eventData as $fieldName => $fieldData) {
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

                    if (!empty($transferData) && $survey_hash != "") {
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
                $sourceData = \REDCap::getData($sourceProjectID, 'array', array(), array($sourceFormFields), array(), array(), false, false, false, "[".$sourcePartIDs[$currentIndex]."] = '".$destIDValue."'");

                if (!empty($sourceData)) {
                    $sourceCompleteValue = "";
                    foreach ($sourceData as $recordID => $currentData) {
                        foreach ($currentData as $eventID => $eventData) {
                            if ($eventID == "repeat_instances") {
                                foreach ($eventData as $subEventID => $subEventData) {
                                    foreach ($subEventData as $subInstrument => $subInstrumentData) {
                                        foreach ($subInstrumentData[$repeat_instance] as $fieldName => $subFieldData) {
                                            if ($fieldName == $sourceCompleteField) {
                                                $sourceCompleteValue = $subFieldData;
                                                //$transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$destCompleteField] = $sourceCompleteValue;
                                            }
                                        }
                                    }
                                }
                            } else {
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