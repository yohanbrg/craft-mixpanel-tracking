<?php 

namespace yohanbrg\craftmixpaneltracking\models;

use craft\base\Model;

class Settings extends Model
{
    public $token = 'xxxxxxxxxxxxxxxxx';
    public $ignoreIpList = '';


    public function defineRules(): array
    {
        return [
            [['token'], 'required'],
            [['ignoreIpList'], 'nullable']
        ];
    }
}