<?php

namespace App\Enums;

enum PricingStatisticScope: string
{
    case DrugCountry = 'drug_country';
    case DrugRegion = 'drug_region';
    case DrugGlobal = 'drug_global';
    case DrugTenderGroup = 'drug_tender_group';
}
