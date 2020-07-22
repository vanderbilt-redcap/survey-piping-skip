<?php
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
session_start();
define("NOAUTH",true);
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "<pre>";
print_r($_POST);
echo "</pre>";
echo "Token: ".$_SESSION['survey_piping_token']."<br/>";
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

        $metaData = $currentProject->metadata;
        foreach ($metaData as $fieldName => $fieldInfo) {
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