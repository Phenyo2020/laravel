<?php

namespace Zidisha\Admin\Form;

use Zidisha\Admin\Setting;
use Zidisha\Form\AbstractForm;

class SettingsForm extends AbstractForm
{

    public function __construct()
    {
        if (!Setting::isUpToDate()) {
            Setting::import();
        }
    }

    public function getSettingsData()
    {
        $data = parent::getData();
        $settingsData = [];
        foreach ($data as $k => $v) {
            $settingsData[str_replace('_', '.', $k)] = $v;
        }
        
        return $settingsData;
    }

    public function getRules($data)
    {
        $rules = [];
        $groups = Setting::getGroups();

        foreach ($groups as $group => $groupSettings) {
            foreach ($groupSettings as $name => $options) {
                if (is_array($options['rule'])) {
                    $rule = array_merge(['required'], $options['rule']);
                } else {
                    $rule = 'required|' . $options['rule'];
                }
                $rules[str_replace('.', '_', $name)] = $rule;
            }
        }

        return $rules;
    }

    public function getDefaultData()
    {
        $data = [];
        foreach (Setting::getAll() as $name => $value) {
            $data[str_replace('.', '_', $name)] = $value;
        }
        
        return $data;
    }
}
