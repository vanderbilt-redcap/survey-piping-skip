<?php
session_start();
$sess_id_1 = session_id();
$sess_id_2 = "survey-module";
session_write_close();
session_id($sess_id_2);
session_start();

define("NOAUTH",true);

$return_type = $_POST['return_type'];
$project_id = $_POST['project_id'];
$record = $_POST['record'];
$instrument = $_POST['instrument'];
$event_id = $_POST['event_id'];
$group_id = $_POST['group_id'];
$survey_hash = $_POST['survey_hash'];
$repeat_instance = $_POST['repeat_instance'];
$check_name = $_POST['check_name'];
$check_value = $_POST['check_value'];

if (!empty($_POST['token']) && hash_equals($_SESSION['survey_piping_token'],$_POST['token'])) {
    $module = new \Vanderbilt\SurveyPipingSkip\SurveyPipingSkip($project_id);
    $validForms = $module->getProjectSetting("dest_form", $project_id);
    $destPartIDs = $module->getProjectSetting('dest_part_id', $project_id);

    if (is_array($validForms)) {
        $question_by_section = $module->findQuestionBySection($project_id, $instrument);
        $currentProject = new \Project($project_id);
        $instrumentRepeats = $currentProject->isRepeatingFormOrEvent($event_id, $instrument);

        $sourceProjects = $module->getProjectSetting('source_project', $project_id);
        $pipeAllFields = $module->getProjectSetting("pipe_all_data", $project_id);
        $pipeFields = $module->getProjectSetting("pipe_fields", $project_id);
        $destForms = $module->getProjectSetting('dest_form', $project_id);

        $metaData = $currentProject->metadata;
        $fieldsOnPage = $module->getFieldsOnForm($metaData, $instrument);

        foreach ($validForms as $currentIndex => $subSetting) {
            if ($destPartIDs[$currentIndex] != $check_name) continue;
            foreach ($subSetting as $formIndex => $vForm) {
                if ($vForm == $instrument) {
                    $sourceProjectID = $sourceProjects[$currentIndex];
                    $transferData = $module->getMatchingRecordData($return_type, $currentIndex, $formIndex, $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $repeat_instance, $check_value);

                    $fieldList = array();
                    $pipeFieldsOnForm = array();
                    $debugInfo = array();

                    $pipeAll = ($pipeAllFields[$currentIndex] == "yes");
                    $pipeFieldsOnForm = $pipeFields[$currentIndex][$formIndex];

                    foreach ($metaData as $fieldName => $fieldInfo) {
                        //$debugInfo['process'] = "before pipeall check";
                        if ($pipeAll === false && !in_array($fieldName, $pipeFieldsOnForm)) continue;
                        //$debugInfo['process'] = "after pipeall check";
                        if ($fieldInfo['form_name'] == $instrument && in_array($fieldName, $fieldsOnPage) && $fieldInfo['element_type'] != 'descriptive') {
                            $fieldList[$fieldName] = $fieldInfo['element_type'];
                        }
                    }

                    if ($instrumentRepeats) {
                        $returnData = $transferData[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance];
                    } else {
                        $returnData = $transferData[$record][$event_id];
                    }
                    echo json_encode(array('data' => $returnData, 'field_types' => $fieldList));
                }
            }
        }
    }
}
session_write_close();
session_id($sess_id_1);
session_start();