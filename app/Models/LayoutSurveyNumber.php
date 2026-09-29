<?php

namespace App\Models;


class LayoutSurveyNumber extends TenantModel
{

    protected $fillable = ['survey_no', 'sub_division', 'extent_sqft', 'guideline_value_sqft'];

}
