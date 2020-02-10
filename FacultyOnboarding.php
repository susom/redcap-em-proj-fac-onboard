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

        $this->emDebug("pid is $project_id / record : $record / inst : $instrument / event : $event_id / target : $target_forms");


        foreach ($target_forms as $sub => $target_form) {

            $this->emDebug("Instrument Matches for $instrument vs $target_form? : ", $instrument == $target_form);

            if ($instrument == $target_form) {

                $migration_event = $this->getProjectSetting('dept-div-form-event')[$sub];
                $target_field = $this->getProjectSetting('sql-field')[$sub];

                //get value for sql field
                $sql_field = $this->getFieldValue($record, $event_id, $target_field);
                $this->emDebug("Instrument matched: Setting Department Division for Sub :  $sub / Form :$target_form /  target_field : $target_field / sql_field : $sql_field");

                if (empty($sql_field)) {
                    //$this->exitAfterHook();  //does this work?
                    $this->emDebug("SQL NOT SET. RETURN NULL");
                    return null;
                }

                //look up corresponding value in dept_div project
                $dept_field = $this->getProjectSetting('lookup-project-dept-field');
                $division_field = $this->getProjectSetting('lookup-project-division-field');
                $dept_email_field = $this->getProjectSetting('lookup-project-department-email-field');
                $div_email_field = $this->getProjectSetting('lookup-project-division-email-field');
                $approver_email_field = $this->getProjectSetting('lookup-project-approver-email-field');

                $dept_div = $this->getDeptDivFromCode($sql_field, array($dept_field, $division_field, $dept_email_field, $div_email_field, $approver_email_field));

                //$this->emDebug("deptfield: $dept_field / division: $division_field / email : $dept_email_field / div+email:  $div_email_field, $approver_email_field / dept_div: $dept_div"); exit;

                //save the labels and emails to the target fields
                $data = array();
                $data = array(
                    REDCap::getRecordIdField() => $record,
                    'redcap_event_name' =>   REDCap::getEventNames(true,false, $event_id)
                );
                    //'overwriteBehavior' =>   'overwrite'); //if field is blank, overwrite with blank

                if (isset($this->getProjectSetting('dept-field')[$sub])) {
                    $data[$this->getProjectSetting('dept-field')[$sub]] = $dept_div[$dept_field];
                }
                if (isset($this->getProjectSetting('div-field')[$sub])) {
                    $data[$this->getProjectSetting('div-field')[$sub]]  = $dept_div[$division_field];
                }

                if (isset($this->getProjectSetting('dept-email-field')[$sub])) {
                    $data[$this->getProjectSetting('dept-email-field')[$sub]] = $dept_div[$dept_email_field];
                }

                if (isset($this->getProjectSetting('div-email-field')[$sub])) {
                    $data[$this->getProjectSetting('div-email-field')[$sub]] = $dept_div[$div_email_field];
                }

                if (isset($this->getProjectSetting('approver-email-field')[$sub])) {
                    $data[$this->getProjectSetting('approver-email-field')[$sub]] = $dept_div[$approver_email_field];
                }


                //$this->emDebug($dept_div, $this->getProjectSetting('dept-field')[$sub], $data, "FOO"); exit;

                $this->emDebug("Saving data: ", $data);
                //todo: is overwrite working for blanking out emails?
                $response = REDCap::saveData('json', json_encode(array($data)), 'overwrite');

                if (!empty($response['errors'])) {
                    $msg =  "Error saving Department and Division labels by Faculty Onboarding EM";
                    $this->emError($msg, $response['errors'], $response, $data);

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


    /**
     * Get the values from the Dept Div lookup project
     *
     * @param $code
     * @param $get_fields
     * @return mixed
     */
    public function getDeptDivFromCode($code, $get_fields) {
        $lookup_project = $this->getProjectSetting('dept-div-lookup-project');

        //given code return department and div
        $params = array(
            'project_id'         => $lookup_project,
            'return_format'        => 'json',
            'records'            => array($code),
            'fields'             => $get_fields  //array($dept_field, $division_field, $dept_email, $div_email, $approver_email)
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

        //$this->emDebug("target is $target_field ", $params, $results, current($results)[$target_field]);

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