<?php

class UnitRserve
{
    protected string $token;
    protected string $url;
    protected string $unit_code;
    protected int $project_id;
    protected array $headers = [];
    protected array $data = [];
    protected $response;
    protected string $message = '';
    protected string $level = '';
    protected int $status = 500;
    protected int $five_mins = 5 * 60;
    protected $national_id_number = false;

    public function __construct()
    {
        if ( ! isset($_SESSION['national_id_number']) ) {
            $_SESSION['national_id_number'] = [];
        }
    }

    public function handle()
    {
        logger("________ handle ( 0 ) ________");
        try {
            if (isset($_SESSION["booking_{$this->unit_code}"])) {
                if (time() - $_SESSION["booking_{$this->unit_code}"] < $this->five_mins) {
                    return $this->reserve();
                }
            }

            logger("national_id_number : $this->national_id_number");
            logger("national_id_numbers : " . json_encode($_SESSION['national_id_number']));
            if ($this->national_id_number) {
                $this->precondition_check($this->national_id_number);
            } else {
                $this->beneficiary_application();
            }
        } catch (\Exception $e) {
            if ($e->getMessage() == "Token is expired") {
                $this->message = "كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)";
            } else {
                $this->message = $e->getMessage();
            }
            $this->status = $e->getCode();
            $this->level = "handle";
        }

        logger("MESSAGE => " . $this->message);
        return $this;
    }

    public function beneficiary_application() // 1
    {
        logger("________ beneficiary_application ( 1 ) ________");
        try {
            $this->setUrl("https://sakani.sa/mainIntermediaryApi/v3/beneficiary/beneficiary_application")
                ->setHeader('Content-Type: application/json; charset=utf-8')
                ->setHeader("authentication: $this->token")
                ->curl();

            if ( isset($this->response->errors) ) {
                throw new \Exception($this->response->errors[0]->detail, $this->response->errors[0]->status);
            }

            $beneficiary_national_id_number = $this->response->data->attributes->beneficiary_national_id_number;
            $this->national_id_number = $beneficiary_national_id_number;
            $_SESSION['national_id_number'][$this->token] = $beneficiary_national_id_number;

            $this->precondition_check( $beneficiary_national_id_number );
        } catch(\Exception $e) {
            logger("ERROR => " . json_encode($e));

            if ($e->getMessage() == "Token is expired") {
                $this->message = "كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)";
            } else {
                $this->message = $e->getMessage();
            }
            $this->status = $e->getCode();
            $this->level = "beneficiary_application";
        }
        
        return $this;
    }

    public function precondition_check(string $national_id_number, bool $do_check = false) // 2
    {
        logger("________ precondition_check ( ".($do_check ? 5 : 2)." ) ________");
        try {
            $this->data = [
                "id" => "beneficiary_sessions",
                "attributes" => [
                    "national_id_number" => $national_id_number,
                    "project_id"         => $this->project_id,
                ]
            ];
            $this->setUrl("https://sakani.sa/mainIntermediaryApi/v4/bookings/precondition_check")
                ->setHeader('Content-Type: application/json; charset=utf-8')
                ->setHeader("authentication: $this->token")
                ->curl();

            $_SESSION["booking_{$this->unit_code}"] = time();

            if ($do_check) {
                $this->booking_precondition_check_completed($this->response->data->request_id);
            } else {
                $this->reserve();
            }
        } catch(\Exception $e) {
            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "precondition_check";
        }
        
        return $this;
    }

    public function reserve() // 3
    {
        logger("________ reserve ( 3 ) ________");
        try {
            $this->data = [
                "attributes" => [
                    "unit_code" => $this->unit_code
                ]
            ];

            $this->setUrl("https://sakani.sa/mainIntermediaryApi/v4/units/reserve")
                    ->setHeader("Content-Type: application/json; charset=utf-8")
                    ->setHeader("authentication: $this->token")
                    ->curl();

            $this->check_eligibility_for_land_booking();
        } catch(\Exception $e) {
            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "reserve";
        }
        
        return $this;
    }

    public function check_eligibility_for_land_booking() // 4
    {
        logger("________ check_eligibility_for_land_booking ( 4 ) ________");
        try {
            $this->data = [];
            $this->setUrl("https://sakani.sa/eligibilityEngineServiceApi/v3/beneficiary_applications/check_eligibility_for_land_booking")
                ->setHeader("Content-Type: application/json; charset=utf-8")
                ->setHeader("authentication: $this->token")
                ->curl();

                $this->precondition_check($this->national_id_number, true);
        } catch(\Exception $e) {
            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "check_eligibility_for_land_booking";
        }

        return $this;
    }

    public function booking_precondition_check_completed(string $request_id) // 6
    {
        logger("________ booking_precondition_check_completed ( 6 ) ________");
        try {
            sleep(.100);
            $this->data = [];
            $this->setUrl("https://sakani.sa/sakani-queries-service/cqrs-res?topic=booking_precondition_check_completed&request_id=$request_id")
                ->setHeader("authentication: $this->token")
                ->setHeader("Content-Encoding: gzip")
                ->curl("GET");

            if ( property_exists($this->response, 'cqrs_status') ) {
                $this->status = 200;
                $this->message = "تم حجز الوحدة {$this->unit_code}. في الوقت " . date('Y-m-d H:i:s') . " / نسبة حجز الوحدة 70% يرجي فحص الحساب";
            } else if ( property_exists($this->response, 'data') && in_array('already_has_active_booking', $this->response->data->block_booking_reason) ) {
                $this->status = 200;
                $this->message = "تم حجز الوحدة {$this->unit_code} بنجاح. في الوقت " . date('Y-m-d H:i:s');
            } else {
                $this->status = 500;
                $this->message = "الوحدة غير متاحة للحجز";
            }

        } catch(\Exception $e) {
            $this->message = "هذه الوحدة غير متاحة للحجز";
            $this->status  = 404;
            $this->level   = "booking_precondition_check_completed";
        }

        return $this;
    }


    /**
     * ********************************************************************************************************
     * ********************************************************************************************************
     * ********************************** HELPER FUNCTIONS ****************************************************
     * ********************************************************************************************************
     * ********************************************************************************************************
    **/

    public function getMessage()
    {
        return [
            'message' => $this->message,
            'status'  => $this->status,
            'level'   => $this->level,
        ];
    }

    public function setHeader(string $header)
    {
        if (!in_array($header, $this->headers)) {
            $this->headers[] = $header;
        }
        return $this;
    }

    public function setProject(int $project_id)
    {
        $this->project_id = $project_id;
        return $this;
    }

    public function setUnit(string $unit_code)
    {
        $this->unit_code = $unit_code;
        return $this;
    }

    public function setToken(string $token)
    {
        $this->token = $token;
        
        if ( isset($_SESSION['national_id_number'][$token]) ) {
            $this->national_id_number = $_SESSION['national_id_number'][$token];
        }
        
        return $this;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    protected function curl(string $method = "POST")
    {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method); 
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode( ["data" => $this->data] ));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36");
        $this->response = json_decode( curl_exec($curl) );
        curl_close($curl);

        logger("URL => [ $method ] => $this->url");
        logger("DATA =>  " . json_encode( ["data" => $this->data] ));
        logger("HEADERS =>  " . json_encode( $this->headers ));
        logger("RESPONSE =>  " . json_encode( $this->response ));

        return $this;
    }
}