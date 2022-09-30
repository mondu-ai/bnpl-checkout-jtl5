<?php

namespace Plugin\MonduPayment\Src\Traits;

use Plugin\MonduPayment\Src\Validations\ValidateInputs;
use Plugin\MonduPayment\Src\Helpers\ArrayValidator;
use Plugin\MonduPayment\Src\Validations\Alerts;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\Debug\Debugger;
use Plugin\MonduPayment\Src\Support\Http\Header;

trait ValidationTrait
{
    public function validated()
    {
        $arrayValidator = new ArrayValidator($this->all());
        if ($arrayValidator->array_keys_exists('kPlugin', 'jtl_token', 'fetch')) {
            $this->unset('kPlugin', 'jtl_token', 'fetch');
        }
        $data = $this->all();
        $validator     = new ValidateInputs($data);
        if ($validator->passing_inputs_throw_validation_rules($this->rules())) {
            $errors = $validator->get_errors();
            if (count($errors) > 0) {
                if ($this->type === 'form') {
                    Alerts::show('danger', $errors);
                } else {
                    return Response::json($errors, 422);
                }
            } else {
                return $validator->get_validated_inputs();
            }
        };
    }
}
