<?php

namespace SRC\Config;

class Jwt
{
    const SECRET = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
    const EXPIRY = 3600; // 1 hour
    const ISSUER = 'cison';
}
