<?php

namespace App\Http\services;

class Summary{

        public function getSummary($data){

            set_time_limit(0);
            $input = json_encode($data);
            $result = exec("python summary.py  $input");

            return strval($result);
        }
}
