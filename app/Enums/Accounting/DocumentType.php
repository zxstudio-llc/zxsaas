<?php

namespace App\Enums\Accounting;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case Bill = 'bill';
}
