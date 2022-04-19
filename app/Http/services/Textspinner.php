<?php

namespace App\Http\services;

class Textspinner{

        public function getText($data){

            set_time_limit(0);
            $input = json_encode($data);
            $result = exec("python textspinner.py  $input");

            return strval($result);
        }
}
