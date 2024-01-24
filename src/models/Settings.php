<?php 

namespace yohanbrg\craftmixpaneltracking\models;

use craft\base\Model;

class Settings extends Model
{
    public $token = 'd';

    public function defineRules(): array
    {
        return [
            [['token'], 'required'],
            // autres règles de validation
        ];
    }
}