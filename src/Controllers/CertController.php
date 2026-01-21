<?php

namespace SRC\Controllers;

use SRC\Utils\Certificate;

class CertController {
    public static function getNextCertNumber() {
        return rest_ensure_response([
            'next_cert_number' => cison_get_next_cert_number(),
            'status' => 'success'
        ]);
    }
}
