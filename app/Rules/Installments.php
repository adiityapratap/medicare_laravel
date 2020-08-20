<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Http\Helpers\AppHelper;

class Installments implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($installments, $insttype, $instamount, $fee)
    {
        $this->installments = $installments;
        $this->insttype = $insttype;
        $this->instamount = $instamount;
        $this->fee = $fee;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $types = array_unique($this->insttype);
        $types = reset($types);
        if($types == 'fixed') {
            return intval($this->fee) == array_sum($value);
        } elseif($types == 'perc') {
            return array_sum($value) == 100;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $types = array_unique($this->insttype);
        if(count($types) > 1) {
            return 'You cannot combine multiple types of installments';
        }
        $types = reset($types);
        if($types == 'fixed') {
            return 'Intallment total does not sum up with the fee amount';
        } elseif($types == 'perc') {
            return 'The distributed installments does not sum up to 100%.';
        }
    }
}
