<?php 

namespace yohanbrg\craftmixpaneltracking\models;

use craft\base\Model;

class Settings extends Model
{
    public $token = 'xxxxxxxxxxxxxxxxx';

    public function defineRules(): array
    {
        return [
            [['token'], 'required'],
        ];
    }
}