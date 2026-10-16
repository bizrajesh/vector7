<?php

/*
| Tamil validation messages for the public marketplace and buyer accounts.
| The promoter and App workspaces always run in English.
*/
return [
    'accepted' => ':attribute ஏற்கப்பட வேண்டும்.',
    'after_or_equal' => ':attribute :date அல்லது அதற்குப் பிந்தைய தேதியாக இருக்க வேண்டும்.',
    'confirmed' => ':attribute உறுதிப்படுத்தல் பொருந்தவில்லை.',
    'date' => ':attribute சரியான தேதி அல்ல.',
    'email' => ':attribute சரியான மின்னஞ்சல் முகவரியாக இருக்க வேண்டும்.',
    'file' => ':attribute ஒரு கோப்பாக இருக்க வேண்டும்.',
    'image' => ':attribute ஒரு படமாக இருக்க வேண்டும்.',
    'in' => 'தேர்ந்தெடுத்த :attribute செல்லாது.',
    'integer' => ':attribute முழு எண்ணாக இருக்க வேண்டும்.',
    'max' => [
        'numeric' => ':attribute :max-ஐ விட அதிகமாக இருக்கக்கூடாது.',
        'file' => ':attribute :max கிலோபைட்டுகளை விட அதிகமாக இருக்கக்கூடாது.',
        'string' => ':attribute :max எழுத்துகளை விட அதிகமாக இருக்கக்கூடாது.',
        'array' => ':attribute-இல் :max-க்கு மேல் உருப்படிகள் இருக்கக்கூடாது.',
    ],
    'mimes' => ':attribute :values வகைக் கோப்பாக இருக்க வேண்டும்.',
    'min' => [
        'numeric' => ':attribute குறைந்தது :min ஆக இருக்க வேண்டும்.',
        'file' => ':attribute குறைந்தது :min கிலோபைட்டுகள் இருக்க வேண்டும்.',
        'string' => ':attribute குறைந்தது :min எழுத்துகள் இருக்க வேண்டும்.',
        'array' => ':attribute-இல் குறைந்தது :min உருப்படிகள் இருக்க வேண்டும்.',
    ],
    'numeric' => ':attribute எண்ணாக இருக்க வேண்டும்.',
    'prohibited' => ':attribute அனுமதிக்கப்படவில்லை.',
    'regex' => ':attribute வடிவம் சரியில்லை.',
    'required' => ':attribute தேவை.',
    'string' => ':attribute உரையாக இருக்க வேண்டும்.',
    'unique' => 'இந்த :attribute ஏற்கனவே பயன்பாட்டில் உள்ளது.',
    'current_password' => 'கடவுச்சொல் தவறு.',
    'password' => [
        'letters' => ':attribute-இல் குறைந்தது ஒரு எழுத்து இருக்க வேண்டும்.',
        'mixed' => ':attribute-இல் குறைந்தது ஒரு பெரிய எழுத்தும் ஒரு சிறிய எழுத்தும் இருக்க வேண்டும்.',
        'numbers' => ':attribute-இல் குறைந்தது ஒரு எண் இருக்க வேண்டும்.',
        'symbols' => ':attribute-இல் குறைந்தது ஒரு குறியீடு இருக்க வேண்டும்.',
        'uncompromised' => 'இந்த :attribute தரவுக் கசிவில் வெளிப்பட்டுள்ளது. வேறொன்றைத் தேர்ந்தெடுக்கவும்.',
    ],

    'attributes' => [
        'name' => 'பெயர்', 'email' => 'மின்னஞ்சல்', 'mobile' => 'மொபைல் எண்', 'message' => 'செய்தி', 'password' => 'கடவுச்சொல்',
        'password_confirmation' => 'கடவுச்சொல் உறுதிப்படுத்தல்', 'current_password' => 'தற்போதைய கடவுச்சொல்', 'consent' => 'ஒப்புதல்',
        'terms' => 'விதிமுறைகள்', 'disclaimer' => 'முன்பதிவு நிபந்தனைகள்', 'subject' => 'பொருள்', 'body' => 'விவரம்', 'details' => 'விவரங்கள்',
        'reason' => 'காரணம்', 'notes' => 'குறிப்புகள்', 'place' => 'ஊர்', 'rating' => 'மதிப்பீடு', 'topic' => 'தலைப்பு', 'address' => 'முகவரி',
        'city' => 'நகரம்', 'district' => 'மாவட்டம்', 'state' => 'மாநிலம்', 'pin' => 'அஞ்சல் குறியீடு', 'promo_code' => 'சலுகைக் குறியீடு',
        'file' => 'கோப்பு', 'category' => 'தலைப்பு', 'q' => 'தேடல்',
    ],
];
