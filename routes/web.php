<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post('/dev/callback', function (Request $request) use ($app){

});

$app->post('/uat/callback', function () use ($app){

});

$app->post('/callback', function () use ($app){

});
//--------DEV

$app->get('/dev/auth',function (Request $request) use ($app){
    $url = env('AUTH_DEV_URL',true);
    $qr_url = env('QR_DEV_URL',true);
    $auth_code = env('AUTH_DEV_CODE',true);
    $client = new Client(['base_uri' => $url]);
    $clientQr = new Client(['base_uri' => $qr_url]);

    $responseAuth = $client->request('POST','/oauth/token',[
        'form_params' => [
            'grant_type' => 'client_credentials'
        ],
        'headers' => [
            'x-test-mode' => 'true',
            'env-id' => 'OAUTH2',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic '.$auth_code
        ]
    ]);
    $code = $responseAuth->getStatusCode();
    $body = json_decode($responseAuth->getBody());
    if($code = 200){
        $access_token = $body->access_token;
        $date_time = new DateTime('NOW');
        $responseQR = $clientQr->request('POST','/v1/qrpayment/request',[
            'headers' => [
                'x-test-mode' => 'true',
                'env-id' => 'QR002',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$access_token
            ],
            'json' => [
                "merchantId" => "KB102057149704",
                "partnerId" => "PTR1051673",
                "partnerSecret" => "d4bded59200547bc85903574a293831b",
                "partnerTxnUid" => "PARTNERTEST0001",
                "qrType" => 3,
                "reference1" => "INV001",
                "reference2" => "HELLOWORLD",
                "reference3" => "INV001",
                "reference4" => "INV001",
                "requestDt" => $date_time->format('c'),
                "txnAmount" => "120.00",
                "txnCurrencyCode" => "THB"
            ]
        ]);
        $bodyQR = json_decode($responseQR->getBody());
        $returnResults = [
            "success" => true,
            "qrCode" => $bodyQR->qrCode,
            "payment_no" => "PARTNERTEST0001",
            "access_token" => $access_token
        ];
        return response()->json($returnResults);
    }else{
        $returnResults = [
            "success" => false
        ];
        return response()->json($returnResults);
    }

});

$app->post('/dev/status',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $payment_no = $bodyJSON->payment_no;
    $date_time = new DateTime('NOW');
    $inquiry_url = env('INQUIRY_DEV_URL',true);
    $client = new Client(['base_uri' => $inquiry_url]);
    $response = $client->request('POST','/v1/qrpayment/inquiry',[
        'headers' => [
            'x-test-mode' => 'true',
            'env-id' => 'QR006',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$access_token
        ],
        'json' => [
            "merchantId" => "KB102057149704",
            "origPartnerTxnUid" => "PARTNERTEST0007",
            "partnerId" => "PTR1051673",
            "partnerSecret" => "d4bded59200547bc85903574a293831b",
            "partnerTxnUid" => "PARTNERTEST0004",
            "requestDt" => $date_time->format('c'),
        ]
    ]);
    $responseBody = json_decode($response->getBody());
    Log::info($responseBody);
    $statusCode = $responseBody->statusCode;
    $txnStatus = $responseBody->txnStatus;
    if($statusCode == "00" && $txnStatus == "PAID"){
        $returnJSON = [
            "success"=> true,
            "txnStatus"=> $txnStatus,
        ];
        return response()->json($returnJSON);
    }else{
        $returnJSON = [
            "success"=> false,
            "txnStatus"=> $txnStatus,
        ];
        return response()->json($returnJSON);
    }

});
//--------END OF DEV
//--------UAT
$app->get('/uat/token',function (Request $request) use ($app){
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $url = env('AUTH_UAT_URL',true);
    $auth_code = env('AUTH_UAT_CODE',true);
    $client = new Client(['base_uri' => $url]);
    $responseAuth = $client->request('POST','/oauth/token',[
        'form_params' => [
            'grant_type' => 'client_credentials'
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic '.$auth_code
        ]
    ]);
    $body = json_decode($responseAuth->getBody());
    $code = $responseAuth->getStatusCode();
    if($code == 200){
        $returnResults = [
            "success" => true,
            "access_token" => $body->access_token,
            "status" => $body->status,
            "expire_in" => $body->expires_in,
            "message" => "success"
        ];

    }else{

        $returnResults = [
            "success" => false,
            "message"=>"Can't Retrieve access token"
        ];

    }
    return response()->json($returnResults);


});

$app->post('/uat/void',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $txn = $bodyJSON->txn;
    $url = env('VOID_URL',true);
    $mid = env('MERCHANT_UAT_ID',true);
    $partnerId = env('PARTNER_UAT_ID',true);
    $partnerSecret = env('PARTNER_UAT_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX01'. $date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/void',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "requestDt" => $date_time->format('c'),
            "terminalId"=>"",
            "origPartnerTxnUid" => $txn,
            "txnNo"=>null
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "partnerTxnUid" => $txnId,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Cancel QR",
            "description"=>$bodyQR->errorDesc,
            "code"=>$bodyQR->errorCode
        ];
    }
    return response()->json($returnResults);
});

$app->post('/uat/cancel',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $txn = $bodyJSON->txn;
    $url = env('CANCEL_URL',true);
    $mid = env('MERCHANT_UAT_ID',true);
    $partnerId = env('PARTNER_UAT_ID',true);
    $partnerSecret = env('PARTNER_UAT_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX01'. $date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/cancel',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "requestDt" => $date_time->format('c'),
            "terminalId"=>null,
            "origPartnerTxnUid" => $txn
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qr_no" => $txn,
            "transaction_no" => $txnId,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Cancel QR",
            "description"=>$bodyQR->errorDesc,
            "code"=>$bodyQR->errorCode
        ];
    }
    return response()->json($returnResults);
});

$app->post('/uat/genqr',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $amount = $bodyJSON->amount;
    $fixTxn = $bodyJSON->txn;
    $ref = $bodyJSON->ref;
    $url = env('AUTH_UAT_URL',true);
    $mid = env('MERCHANT_UAT_ID',true);
    $partnerId = env('PARTNER_UAT_ID',true);
    $partnerSecret = env('PARTNER_UAT_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX01'. $date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/request',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $fixTxn,
            "qrType" => "3",
            "reference1" => $ref,
            "reference2" => "",
            "reference3" => "",
            "reference4" => "",
            "requestDt" => $date_time->format('c'),
            "txnAmount" => $amount,
            "txnCurrencyCode" => "THB",
            "metadata" => "รูปถ่าย ".$amount
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qrCode" => $bodyQR->qrCode,
            "payment_no" => $fixTxn,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get QR Data",
            "description"=>$bodyQR->errorCode,
            "code"=>$bodyQR->errorCode
        ];
    }
    return response()->json($returnResults);
});

$app->post('/uat/qrcode',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $url = env('AUTH_UAT_URL',true);
    $mid = env('MERCHANT_UAT_ID',true);
    $partnerId = env('PARTNER_UAT_ID',true);
    $partnerSecret = env('PARTNER_UAT_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX01'. $date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/request',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "qrType" => "3",
            "reference1" => "SIXSHEET_LOCATION",
            "reference2" => "",
            "reference3" => "",
            "reference4" => "",
            "requestDt" => $date_time->format('c'),
            "txnAmount" => "120.00",
            "txnCurrencyCode" => "THB",
            "metadata" => "รูปถ่าย 120.00"
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qrCode" => $bodyQR->qrCode,
            "payment_no" => $txnId,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get QR Data"
        ];
    }
    return response()->json($returnResults);
});

$app->post('/uat/status',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $payment_no = $bodyJSON->payment_no;
    $date_time = new DateTime('NOW');
    $inquiry_url = env('INQUIRY_UAT_URL',true);
    $mid = env('MERCHANT_UAT_ID',true);
    $partnerId = env('PARTNER_UAT_ID',true);
    $partnerSecret = env('PARTNER_UAT_SECRET',true);
    $client = new Client(['base_uri' => $inquiry_url]);
    $txnId = 'SX02'. $date_time->format('Ymd-His');
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $response = $client->request('POST','/v1/qrpayment/inquiry',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "origPartnerTxnUid" => $payment_no,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "requestDt" => $date_time->format('c'),
        ]
    ]);
    $code = $response->getStatusCode();
    $body = json_decode($response->getBody());
    if($code == 200 && $body->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "status" => $body->txnStatus,
            "payment_no" => $payment_no,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get Payment Data"
        ];
    }
    return response()->json($returnResults);
});

//----> END OF UAT
//----> PROD

$app->post('/redeem',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $coupon = $bodyJSON->code;
    $machine_no = $bodyJSON->machine_no;
    $sixsheet_url = env('SIXSHEET_COUNPON_URL',true);
    $client = new Client(['base_uri' => $sixsheet_url]);
    try {
        $response = $client->request('POST', 'coupon/wp-json/wc/v3/orders', [
            'auth' => [
                'ck_5c6e461c46964b27bb9c3f7ead14c50aac057b63',
                'cs_f31eed49fc2fbd49c407ea9758a28cc0c0b37bd2'
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'cache-control' => 'no-cache'
            ],
            'json' => [
                'payment_method' => 'bacs',
                'payment_method_title' => 'Direct Bank Transfer',
                'set_paid' => true,
                'status' => "completed",
                'billing' => [
                    'first_name' => 'Coupon ' . $machine_no,
                ],
                'line_items' => [[
                    "product_id" => 1130,
                    "quantity" => 1
                ]],
                "coupon_lines" => [[
                    "code" => $coupon
                ]]
            ]
        ]);

        $code = $response->getStatusCode();
        if($code == 201 || $code == 200){
            $returnResults = [
                "success" => true,
                "message" => "Redeemed"
            ];
        }else{
            // WENT WRONG
            $returnResults = [
                "success" => false,
                "message" => "Can't redeem coupon"
            ];
        }
        return response()->json($returnResults);
    }
    catch(GuzzleHttp\Exception\BadResponseException $e){
        $response = $e->getResponse();
        $responseBodyAsString = json_decode($response->getBody());
        $returnResults = [
            "success" => false,
            "message" => $responseBodyAsString->message
        ];
        return response()->json($returnResults);
    }
});

$app->post('/new/redeem',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $coupon = $bodyJSON->code;
    $machine_no = $bodyJSON->machine_no;
    $sixsheet_url = env('SIXSHEET_NEW_URL',true);
    $client = new Client(['base_uri' => $sixsheet_url]);
    try {
        $response = $client->request('POST', 'wp-json/wc/v3/orders', [
            'auth' => [
                    'ck_2f42a702664b813f6b126d62226b7ef5b70dd46b',
                    'cs_79aa6fc265772a6eb48f6697004f8c6a60701dd4'
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'cache-control' => 'no-cache'
            ],
            'json' => [
                'payment_method' => 'bacs',
                'payment_method_title' => 'Direct Bank Transfer',
                'set_paid' => true,
                'status' => "completed",
                'billing' => [
                    'first_name' => 'Coupon ' . $machine_no,
                ],
                'line_items' => [[
                    "product_id" => 1130,
                    "quantity" => 1
                ]],
                "coupon_lines" => [[
                    "code" => $coupon
                ]]
            ]
        ]);

        $code = $response->getStatusCode();
        if($code == 201 || $code == 200){
            $returnResults = [
                "success" => true,
                "message" => "Redeemed"
            ];
        }else{
            // WENT WRONG
            $returnResults = [
                "success" => false,
                "message" => "Can't redeem coupon"
            ];
        }
        return response()->json($returnResults);
    }
    catch(GuzzleHttp\Exception\BadResponseException $e){
        $response = $e->getResponse();
        $responseBodyAsString = json_decode($response->getBody());
        $returnResults = [
            "success" => false,
            "message" => $responseBodyAsString->message
        ];
        return response()->json($returnResults);
    }
});

$app->post('/coupon',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $coupon = $bodyJSON->code;
    $date_time = new DateTime('NOW');
    $sixsheet_url = env('SIXSHEET_COUNPON_URL',true);
    $client = new Client(['base_uri' => $sixsheet_url]);
    $response = $client->request('GET','coupon/wp-json/wc/v3/coupons?search='.$coupon,[
        'auth' => [
            'ck_5c6e461c46964b27bb9c3f7ead14c50aac057b63',
            'cs_f31eed49fc2fbd49c407ea9758a28cc0c0b37bd2'
        ],
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache'
        ]
    ]);
    $code = $response->getStatusCode();
    $body = json_decode($response->getBody());

    if($code == 200 && sizeof($body) >= 1){
        // Check If Code is Validate
        $usage_count = $body[0]->usage_count;
        $usage_limit = $body[0]->usage_limit;
        $discount_type = $body[0]->discount_type;
        $date_expires = new DateTime($body[0]->date_expires);
        $amount = $body[0]->amount;
        // Check Usage Limit
        if($usage_count < $usage_limit){
            if($date_time <= $date_expires) {
                if ($discount_type == 'for_free') {
                    // FREE COUPON
                    $returnResults = [
                        "success" => true,
                        "amount" => 0,
                        "is_free" => true,
                        "message" => "this is free coupon"
                    ];

                } else {
                    // OTHERS
                    $returnResults = [
                        "success" => true,
                        "amount" => $amount,
                        "is_free" => false,
                        "message" => "this is discount coupon"
                    ];
                }
            }else{
                // EXPIRED
                $returnResults = [
                    "success" => false,
                    "amount" => 0,
                    "is_free" => false,
                    "message" => "The code you entered is incorrect or is no longer valid."
                ];
            }
        }else{
            // USAGE LIMIT REACHED
            $returnResults = [
                "success" => false,
                "amount" => 0,
                "is_free" => false,
                "message" => "The coupon code has already been redeemed."
            ];
        }

    }else{
        // WENT WRONG
        $returnResults = [
            "success" => false,
            "amount" => 0,
            "is_free" => false,
            "message" => "The code you entered is incorrect or is no longer valid."
        ];
    }
    return response()->json($returnResults);
});

$app->post('/new/coupon',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $coupon = $bodyJSON->code;
    $date_time = new DateTime('NOW');
    $sixsheet_url = env('SIXSHEET_NEW_URL',true);
    $client = new Client(['base_uri' => $sixsheet_url]);
    $response = $client->request('GET','wp-json/wc/v3/coupons?search='.$coupon,[
        'auth' => [
            'ck_2f42a702664b813f6b126d62226b7ef5b70dd46b',
            'cs_79aa6fc265772a6eb48f6697004f8c6a60701dd4'
        ],
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache'
        ]
    ]);
    $code = $response->getStatusCode();
    $body = json_decode($response->getBody());

    if($code == 200 && sizeof($body) >= 1){
        // Check If Code is Validate
        $usage_count = $body[0]->usage_count;
        $usage_limit = $body[0]->usage_limit;
        $discount_type = $body[0]->discount_type;
        $date_expires = new DateTime($body[0]->date_expires);
        $amount = $body[0]->amount;
        // Check Usage Limit
        if($usage_count < $usage_limit){
            if($date_time <= $date_expires) {
                if ($discount_type == 'for_free') {
                    // FREE COUPON
                    $returnResults = [
                        "success" => true,
                        "amount" => 0,
                        "is_free" => true,
                        "message" => "this is free coupon"
                    ];

                } else {
                    // OTHERS
                    $returnResults = [
                        "success" => true,
                        "amount" => $amount,
                        "is_free" => false,
                        "message" => "this is discount coupon"
                    ];
                }
            }else{
                // EXPIRED
                $returnResults = [
                    "success" => false,
                    "amount" => 0,
                    "is_free" => false,
                    "message" => "The code you entered is incorrect or is no longer valid."
                ];
            }
        }else{
            // USAGE LIMIT REACHED
            $returnResults = [
                "success" => false,
                "amount" => 0,
                "is_free" => false,
                "message" => "The coupon code has already been redeemed."
            ];
        }

    }else{
        // WENT WRONG
        $returnResults = [
            "success" => false,
            "amount" => 0,
            "is_free" => false,
            "message" => "The code you entered is incorrect or is no longer valid."
        ];
    }
    return response()->json($returnResults);
});

$app->get('/token',function (Request $request) use ($app){
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $url = env('AUTH_PRD_URL',true);
    $date_time = new DateTime('NOW');
    $auth_code = env('AUTH_PRD_CODE',true);
    $client = new Client(['base_uri' => $url]);
    $responseAuth = $client->request('POST','/oauth/token',[
        'form_params' => [
            'grant_type' => 'client_credentials'
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic '.$auth_code
        ]
    ]);
    $body = json_decode($responseAuth->getBody());
    $code = $responseAuth->getStatusCode();
    Log::info('Token '.$date_time->format('c').'->'.$body->access_token.','.$body->status.','.$body->expires_in);
    if($code == 200){
        $returnResults = [
            "success" => true,
            "access_token" => $body->access_token,
            "status" => $body->status,
            "expire_in" => $body->expires_in,
            "message" => "success"
        ];

    }else{

        $returnResults = [
            "success" => false,
            "message"=>"Can't Retrieve access token"
        ];

    }
    return response()->json($returnResults);
});

$app->post('/status',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $payment_no = $bodyJSON->payment_no;
    $date_time = new DateTime('NOW');
    $inquiry_url = env('INQUIRY_PRD_URL',true);
    $mid = env('MERCHANT_PRD_ID',true);
    $partnerId = env('PARTNER_PRD_ID',true);
    $partnerSecret = env('PARTNER_PRD_SECRET',true);
    $client = new Client(['base_uri' => $inquiry_url]);
    $txnId = 'SXST'. $date_time->format('Ymd-His');
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $response = $client->request('POST','/v1/qrpayment/inquiry',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "origPartnerTxnUid" => $payment_no,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "requestDt" => $date_time->format('c'),
        ]
    ]);
    $code = $response->getStatusCode();
    $body = json_decode($response->getBody());
    Log::info('Status '.$date_time->format('c').'->'.$body->txnStatus.','.$payment_no.','.$access_token);
    if($code == 200 && $body->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "status" => $body->txnStatus,
            "payment_no" => $payment_no,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get Payment Data"
        ];
    }
    return response()->json($returnResults);
});

$app->post('/qrcode',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $machine_no = $bodyJSON->machine_no;
    $url = env('AUTH_PRD_URL',true);
    $mid = env('MERCHANT_PRD_ID',true);
    $partnerId = env('PARTNER_PRD_ID',true);
    $partnerSecret = env('PARTNER_PRD_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX'.$machine_no.$date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/request',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "qrType" => "3",
            "reference1" => "SIXSHEET_LOCATION",
            "reference2" => "",
            "reference3" => "",
            "reference4" => "",
            "requestDt" => $date_time->format('c'),
            "txnAmount" => "120.00",
            "txnCurrencyCode" => "THB",
            "metadata" => "รูปถ่าย 120.00"
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    Log::info('QR '.$date_time->format('c').'->'.$txnId.','.$access_token);
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qrCode" => $bodyQR->qrCode,
            "payment_no" => $txnId,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get QR Data"
        ];
    }
    return response()->json($returnResults);
});

$app->post('/qrcode140',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $machine_no = $bodyJSON->machine_no;
    $url = env('AUTH_PRD_URL',true);
    $mid = env('MERCHANT_PRD_ID',true);
    $partnerId = env('PARTNER_PRD_ID',true);
    $partnerSecret = env('PARTNER_PRD_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX'.$machine_no.$date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/request',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "qrType" => "3",
            "reference1" => "SIXSHEET_LOCATION",
            "reference2" => "",
            "reference3" => "",
            "reference4" => "",
            "requestDt" => $date_time->format('c'),
            "txnAmount" => "140.00",
            "txnCurrencyCode" => "THB",
            "metadata" => "รูปถ่าย 140.00"
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    Log::info('QR '.$date_time->format('c').'->'.$txnId.','.$access_token);
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qrCode" => $bodyQR->qrCode,
            "payment_no" => $txnId,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get QR Data"
        ];
    }
    return response()->json($returnResults);
});

$app->post('/custom_qrcode',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $machine_no = $bodyJSON->machine_no;
    $amount = $bodyJSON->amount;
    $url = env('AUTH_PRD_URL',true);
    $mid = env('MERCHANT_PRD_ID',true);
    $partnerId = env('PARTNER_PRD_ID',true);
    $partnerSecret = env('PARTNER_PRD_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX'.$machine_no.$date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/request',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "qrType" => "3",
            "reference1" => "SIXSHEET_LOCATION",
            "reference2" => "",
            "reference3" => "",
            "reference4" => "",
            "requestDt" => $date_time->format('c'),
            "txnAmount" => $amount,
            "txnCurrencyCode" => "THB",
            "metadata" => "รูปถ่าย ".$amount
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    Log::info('QR '.$date_time->format('c').'->'.$txnId.','.$access_token);
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qrCode" => $bodyQR->qrCode,
            "payment_no" => $txnId,
            "amount" => $amount,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Get QR Data"
        ];
    }
    return response()->json($returnResults);
});

$app->post('/cancel',function (Request $request) use ($app){
    $bodyContent = $request->getContent();
    $bodyJSON = json_decode($bodyContent);
    $access_token = $bodyJSON->access_token;
    $txn = $bodyJSON->txn;
    $url = env('CANCEL_PRD_URL',true);
    $mid = env('MERCHANT_PRD_ID',true);
    $partnerId = env('PARTNER_PRD_ID',true);
    $partnerSecret = env('PARTNER_PRD_SECRET',true);
    $crt_file = app_path('sixsheet-crt/sixsheet_me.crt');
    $crt_key = app_path('sixsheet-crt/sixsheet_me.key');
    $date_time = new DateTime('NOW');
    $txnId = 'SX01'. $date_time->format('Ymd-His');
    $client = new Client(['base_uri' => $url]);
    $response = $client->request('POST','/v1/qrpayment/cancel',[
        'headers' => [
            'Content-Type' => 'application/json',
            'cache-control' => 'no-cache',
            'Authorization' => 'Bearer '.$access_token
        ],
        'cert' => $crt_file,
        'ssl_key' => $crt_key,
        'json' => [
            "merchantId" => $mid,
            "partnerId" => $partnerId,
            "partnerSecret" => $partnerSecret,
            "partnerTxnUid" => $txnId,
            "requestDt" => $date_time->format('c'),
            "terminalId"=>null,
            "origPartnerTxnUid" => $txn
        ]
    ]);
    $code = $response->getStatusCode();
    $bodyQR = json_decode($response->getBody());
    if($code == 200 && $bodyQR->statusCode == "00") {
        $returnResults = [
            "success" => true,
            "qr_no" => $txn,
            "transaction_no" => $txnId,
            "access_token" => $access_token
        ];
    }else{
        $returnResults = [
            "success" => false,
            "message"=>"Can't Cancel QR",
            "description"=>$bodyQR->errorDesc,
            "code"=>$bodyQR->errorCode
        ];
    }
    return response()->json($returnResults);
});

$app->get('/dir', function () use ($app) {
    return $app->version();
});
