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
$response_id = $_POST['response_id'];
$repeat_instance = $_POST['repeat_instance'];

if (!empty($_POST['token'])) {
    if (hash_equals($_SESSION['survey_piping_token'],$_POST['token'])) {
        $check_value = $_POST['check_value'];
        $question_by_section = $module->findQuestionBySection($project_id, $instrument);
        $currentProject = new \Project($project_id);
        $instrumentRepeats = $currentProject->isRepeatingFormOrEvent($event_id, $instrument);

        $sourceProjects = $module->getProjectSetting('source_project',$project_id);
        $pipeAllFields = $module->getProjectSetting("pipe_all_data",$project_id);
        $pipeFields = $module->getProjectSetting("pipe_fields",$project_id);
        $destForms = $module->getProjectSetting('dest_form',$project_id);

        list($transferData, $currentIndex, $formIndex) = $module->getMatchingRecordData($return_type, $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance, $check_value);

        $surveyObject = new \Survey();
        list ($pageFields, $totalPages) = $surveyObject::getPageFields($instrument, $question_by_section);
        list ($saveBtnText, $hideFields, $isLastPage) = $surveyObject::setPageNum($pageFields, $totalPages);
        if (!in_array($currentProject->table_pk, $hideFields)) {
            $hideFields[] = $currentProject->table_pk;
        }
        if (!in_array($instrument . "_complete", $hideFields)) {
            $hideFields[] = $instrument . "_complete";
        }

        $fieldsOnPage = array_diff($module->getFieldsOnForm($currentProject->metadata, $instrument), $hideFields);

        $fieldList = array();
        $pipeFieldsOnForm = array();
        $currentIndex = "";
        $pipeAll = false;

        foreach ($sourceProjects as $topIndex => $sourceID) {
            if ($sourceID == $project_id && $pipeAllFields[$topIndex] == "yes") {
                $pipeAll = true;
            }
            foreach ($destForms[$topIndex] as $bottomIndex => $destForm) {
                if ($destForm == $instrument) {
                    $pipeFieldsOnForm = array_merge($pipeFieldsOnForm, $pipeFields[$topIndex][$bottomIndex]);
                }
            }


            $metaData = $currentProject->metadata;
            foreach ($metaData as $fieldName => $fieldInfo) {
                if ($pipeAll == false && !in_array($fieldName, $pipeFieldsOnForm)) continue;
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
session_write_close();
session_id($sess_id_1);
session_start();