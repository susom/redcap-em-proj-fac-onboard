<?php

namespace Stanford\FacultyOnboarding;

use ExternalModules\ExternalModules;
use REDCap;

class FacultyOnboarding extends \ExternalModules\AbstractExternalModule
{

    public function redcap_save_record($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance)
    {
        //iterate through all of the sub_settings

        $target_forms = $this->getProjectSetting('dept-div-form');




        foreach ($target_forms as $sub => $target_form) {

            if ($instrument == $target_form) {
                $migration_event = $this->getProjectSetting('dept-div-form-event')[$sub];
                $target_field = $this->getProjectSetting('sql-field')[$sub];

                //get value for sql field
                $sql_field = $this->getFieldValue($record, $event_id, $target_field);

                if (empty($sql_field)) {
                    //$this->exitAfterHook();  //does this work?
                    return null;
                }

                //look up corresponding value in dept_div project
                $dept_div = $this->getDeptDivFromCode($sql_field);
                $dept = $dept_div[$this->getProjectSetting('lookup-project-dept-field')];
                $div = $dept_div[$this->getProjectSetting('lookup-project-division-field')];
                //$this->emDebug($dept_div,$dept, $div, "FOO");

                //save the labels to the target fields
                $data = array(
                    REDCap::getRecordIdField() => $record,
                    'redcap_event_name' =>   REDCap::getEventNames(true,false, $event_id),
                    $this->getProjectSetting('dept-field')[$sub] => $dept,
                    $this->getProjectSetting('div-field')[$sub]  => $div
                );

                $response = REDCap::saveData('json', json_encode(array($data)));

                if (!empty($response['errors'])) {
                    $msg =  "Error saving Department and Division labels by Faculty Onboarding EM";
                    $this->emError($msg);

                    REDCap::logEvent(
                        $msg,  //action
                        "Unable to set labels for this department division code: " . $sql_field . " " . $response['errors'],
                        NULL, //sql optional
                        $record //record optional
                    );

                }

            }
        }

    }


    public function getDeptDivFromCode($code) {
        $lookup_project = $this->getProjectSetting('dept-div-lookup-project');
        $dept_field = $this->getProjectSetting('lookup-project-dept-field');
        $division_field = $this->getProjectSetting('lookup-project-division-field');

        //given code return department and div
        $params = array(
            'project_id'         => $lookup_project,
            'return_format'        => 'json',
            'records'            => array($code),
            'fields'             => array($dept_field, $division_field)
        );

        $q = REDCap::getData($params);
        $results = json_decode($q, true);
        //$this->emDebug($params, $q ,$results);

        return current($results);

    }

    public function getFieldValue($record, $event_id, $target_field) {
        $params = array(
            'return_format' => 'json',
            'records' => $record,
            'fields' => array($target_field),
            'events' => $event_id
        );

        $q = REDCap::getData($params);
        $results = json_decode($q, true);

        return current($results)[$target_field];
    }


    function emLog()
    {
        global $module;
        $emLogger = ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($module->PREFIX, func_get_args(), "INFO");
    }

    function emDebug()
    {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || (!empty($_GET['pid']) && $this->getProjectSetting('enable-project-debug-logging'))) {
            $emLogger = ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError()
    {
        $emLogger = ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }

}