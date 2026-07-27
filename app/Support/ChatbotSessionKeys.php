<?php

namespace App\Support;

class ChatbotSessionKeys
{
    public const SESSION_VERIFIED = 'captcha_verified';
    public const SESSION_VERIFIED_AT = 'captcha_verified_at';
    public const SESSION_CHALLENGE_ID = 'captcha_challenge_id';
    public const SESSION_CHALLENGE_TOKEN = 'captcha_challenge_token';
    public const SESSION_CHATBOT_USER_ID = 'chatbot_user_id';
    public const SESSION_CHATBOT_OTP_VERIFIED = 'chatbot_otp_verified';
    public const SESSION_OTP_LAST_SENT_AT = 'chatbot_otp_last_sent_at';
    public const SESSION_OTP_VERIFY_ATTEMPTS = 'chatbot_otp_verify_attempts';
}
