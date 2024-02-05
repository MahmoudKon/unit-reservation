<?php

class UnitRserve
{
    protected string $token;
    protected string $url;
    protected string $unit_code;
    protected $unit_id;
    protected int $project_id;
    protected array $headers = [];
    protected array $data = [];
    protected $response;
    protected string $message = '';
    protected string $level = '';
    protected int $status = 500;
    protected int $five_mins = 5 * 60;
    protected bool $do_esc = true;
    protected $national_id_number = false;

    public function __construct()
    {
        if ( ! isset($_SESSION['national_id_number']) ) {
            $_SESSION['national_id_number'] = [];
        }
    }

    public function handle(?int $unit_id = null, bool $do_esc = true)
    {
        logger("________ handle ________");
        try {
            $this->unit_id = $unit_id;
            $this->do_esc  = $do_esc;
            // if ($unit_id) {
            //     if (! $this->checkUnitIsBooked($unit_id)) {
            //         logger("checkUnitIsBooked >>>>> $unit_id");
            //         return $this;
            //     }
            // }

            if ($do_esc && false) {
                if (isset($_SESSION["booking_{$this->unit_code}"])) {
                    if (time() - $_SESSION["booking_{$this->unit_code}"] < $this->five_mins) {
                        return $this->reserve();
                    }
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
            logger("ERROR => " . $e->getMessage());

            if ($e->getMessage() == "Token is expired") {
                $this->message = "كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)";
            } else {
                $this->message = $e->getMessage();
            }
            $this->status = $e->getCode();
            $this->level = "handle";
        }

        return $this;
    }

    protected function checkUnitIsBooked(int $unit_id)
    {
        logger("________ checkUnitIsBooked (0)________");
        try {
            $this->data = [];
            $this->setUrl("https://sakani.sa/mainIntermediaryApi/v3/units/{$unit_id}")
                        ->setHeader('Content-Type: application/json; charset=utf-8')
                        ->curl("GET");

            if (!$this->response || !isset($this->response->data)) {
                throw new Exception("هذه الوحدة غير موجودة", 403);
            }

            if ($this->response->data->attributes->booking_status != 'available') {
                throw new Exception("هذه الوحدة  {$this->response->data->attributes->unit_code} غير متاحة للحجز", 200);
            }
        } catch (\Exception $e) {
            logger("ERROR => " . $e->getMessage());

            if ($e->getMessage() == "Token is expired") {
                $this->message = "كود العميل غير صالح (تم انتهاء صلحية الكود برجاء ادخال كود أخر)";
            } else {
                $this->message = $e->getMessage();
            }
            $this->status = $e->getCode();
            $this->level = "checkUnitIsBooked";

            return false;
        }
        return true;
    }

    public function beneficiary_application() // 1
    {
        logger("________ beneficiary_application (1) ________");
        try {
            $this->setUrl("https://sakani.sa/mainIntermediaryApi/v3/beneficiary/beneficiary_application")
                ->setHeader('Content-Type: application/json; charset=utf-8')
                ->setHeader("authentication: $this->token")
                ->curl();

            if ( isset($this->response->errors) ) {
                throw new \Exception($this->response->errors[0]->detail, $this->response->errors[0]->status);
            }

            $beneficiary_national_id_number = $this->response->data->attributes->beneficiary_national_id_number;
            $_SESSION['national_id_number'][$this->token] = $beneficiary_national_id_number;

            $this->precondition_check( $beneficiary_national_id_number );
            return $this;
        } catch(\Exception $e) {
            logger("ERROR => " . $e->getMessage());

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

    public function precondition_check(string $national_id_number) // 2
    {
        logger("________ precondition_check (2) ________");
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

            // $this->booking_precondition_check_completed($this->response->data->request_id);
            $_SESSION["booking_{$this->unit_code}"] = time();
            $this->reserve();
        } catch(\Exception $e) {
            logger("ERROR => " . $e->getMessage());

            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "precondition_check";
        }
        
        return $this;
    }

    //  NOT USED
    public function booking_precondition_check_completed(string $request_id) // 3
    {
        logger("________ booking_precondition_check_completed (3) ________");
        try {
            $this->data = [];
            $this->setUrl("https://sakani.sa/sakani-queries-service/cqrs-res?topic=booking_precondition_check_completed&request_id=$request_id")
                ->setHeader("authentication: $this->token")
                ->setHeader("Content-Encoding: gzip")
                ->curl("GET");

            if ( property_exists($this->response, 'cqrs_status') ) {
                throw new \Exception("Recall", 201);
            }

            if ( !isset($this->response->data) ) {
                throw new \Exception('لا يمكن حجز الوحده', 404);
            }

            if ( in_array('already_has_active_booking', $this->response->data->block_booking_reason) ) {
                throw new \Exception('لديك حجز مسبق', 404);
            }

            if (property_exists($this->response->data, 'errors')) {
                throw new \Exception($this->response->data->title, $this->response->data->status);
            }

            $this->reserve();
        } catch(\Exception $e) {
            logger("ERROR => " . $e->getMessage());

            if ($e->getCode() == 201 || $e->getMessage() == "Recall") {
                return $this->handle($this->unit_id, $this->do_esc);
            } else {
                $this->message = $e->getMessage();
                $this->status = $e->getCode();
                $this->level = "booking_precondition_check_completed";
            }
        }

        return $this;
    }

    public function reserve() // 4
    {
        logger("________ reserve (4) ________");
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

            if (is_null($this->response)) {
                throw new \Exception("", 500);
            }

            if (property_exists($this->response->data, 'errors')) {
                throw new \Exception($this->response->data->title, $this->response->data->status);
            }

            $this->reserve_unit_completed($this->response->data->request_id);
        } catch(\Exception $e) {
            logger("ERROR => " . $e->getMessage());

            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "reserve";
        }
        
        return $this;
    }

    public function reserve_unit_completed(string $request_id) // 5
    {
        logger("________ reserve_unit_completed (5) ________");
        try {
            sleep(1);
            $this->data = [];
            $this->setUrl("https://sakani.sa/sakani-queries-service/cqrs-res?topic=reserve_unit_completed&request_id=$request_id")
                    ->setHeader("authentication: $this->token")
                    ->setHeader("Content-Type: application/json; charset=utf-8")
                    ->setHeader("Content-Encoding: gzip")
                    ->curl("GET");

            if (is_null($this->response) || property_exists($this->response, 'cqrs_status')) {
                throw new \Exception('Recall', 201);
            }

            if (is_null($this->response) || !property_exists($this->response, 'data')) {
                throw new \Exception("الوحدة {$this->unit_code} غير متاحة للحجز", 200);
            }

            if (property_exists($this->response->data, 'errors') && $this->response->data->errors && count($this->response->data->errors) > 0) {
                $error = $this->response->data->errors[0];
                if ($error->title == 'project_does_not_match_the_token') {
                    throw new \Exception("المشروع {$this->project_id}  غير متاح", 422);
                } else if ($error->title == 'invalid_available_unit') {
                    throw new \Exception("الوحدة {$this->unit_code} غير متاحة للحجز", 200);
                } else if ($error->title == 'already_has_reserved_unit') {
                } else {
                    throw new \Exception("الوحدة {$this->unit_code} غير متاحة للحجز", 200);
                }
            }

            $unit_data = $this->response->data->unit ?? null;
            if (! $unit_data || $unit_data->data->attributes->booking_status == 'booked') {
                throw new Exception("هذه الوحدة {$this->unit_code} غير متاحة للحجز", 200);
            }

            logger("Item Unit : " . json_encode($this->response->data->unit));
            $name = $this->getName();
            $this->check_eligibility_for_land_booking($name);
        } catch(\Exception $e) {
            logger("ERROR => " . $e->getMessage());

            if ($e->getCode() == 201 || $e->getMessage() == 'Recall') {
                return $this->handle($this->unit_id, $this->do_esc);
            }

            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "reserve_unit_completed";
        }
        return $this;
    }

    public function getName()
    {
        $unit_data = $this->response->data->unit ?? null;
        $name = "";
        logger("Item Unit : " . json_encode($this->response->data->unit));
        foreach ($unit_data->included as $row) {
            logger("Item Row : " . json_encode($row));
            $name = $row->attributes->name;
        }

        return $name;
    }

    public function check_eligibility_for_land_booking(string $name = '') // 6
    {
        logger("________ check_eligibility_for_land_booking (6) ________");
        try {
            $this->data = [];
            $this->setUrl("https://sakani.sa/eligibilityEngineServiceApi/v3/beneficiary_applications/check_eligibility_for_land_booking")
                ->setHeader("Content-Type: application/json; charset=utf-8")
                ->setHeader("authentication: $this->token")
                ->curl();

                $this->status = 200;
                if ( is_null($this->response) || isset($this->response->errors) ) {
                    $this->message = "الوحدة {$this->unit_code}  غير متاحة للحجز";
                } else {
                    $this->message = "تم حجز الوحدة {$this->unit_code} بنجاح. عن طريق المستخدم {$name} في الوقت " . date('Y-m-d H:i:s');
                }
        } catch(\Exception $e) {
            logger("ERROR => " . $e->getMessage());

            $this->message = $e->getMessage();
            $this->status = $e->getCode();
            $this->level = "check_eligibility_for_land_booking";
        }
        
        return $this;
    }

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