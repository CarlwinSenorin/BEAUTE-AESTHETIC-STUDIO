<?php
/**
 * Chatbot Knowledge Base
 * Static information about the business, policies, and FAQs.
 */

return [
    'business_info' => [
        'name'     => 'Beaute Aesthetic Studio',
        'location' => 'Legazpi City',
        'address'  => 'Casa Erin Building, Cabagnan, Legazpi City',
        'contact'  => [
            'phone' => '09171086478',
            'email' => 'info@beauteaesthetic.com'
        ],
        'hours' => [
            'Monday - Sunday' => '8:00 AM - 5:00 PM',
            'general'         => 'We are open daily from **8:00 AM to 5:00 PM**, including weekends and holidays.',
            'timeslots'       => 'Sessions range from 30 to 120 minutes. Appointments are scheduled per service duration.'
        ],
        'peak_hours' => '⚠️ **Peak hours** are midmornings and early afternoons. We recommend booking in advance!',
        'owner'      => 'Franz Edgie Firaza'
    ],

    'studio_info' => [
        'owner'   => 'Franz Edgie Firaza',
        'mission' => 'To provide premium aesthetic services that enhance natural beauty and promote holistic wellness in a relaxing environment.',
        'vision'  => 'To be the leading aesthetic studio in Legazpi City, recognized for professional expertise and exceptional customer care.',
        'about'   => 'Beaute Aesthetic Studio is a premier wellness destination led by **Franz Edgie Firaza**, specializing in professional skin, nail, and lash treatments. Our team of certified specialists uses state-of-the-art equipment to ensure the best results for every client.'
    ],

    'staff' => [
        'summary' => 'We have a growing team of certified professional specialists. You can select your preferred specialist during booking, or choose "Any Available" and we will assign the best available for you!',
        'list'    => [
            // Dynamic list is pulled from the database; this is a fallback placeholder
            ['name' => 'Our Team', 'role' => 'Certified Beauty Specialists', 'specialty' => 'Nails, Lashes, Skin, Massage & More']
        ]
    ],

    'policies' => [
        'cancellation'   => '❌ **Cancellation Policy:** We require at least **24 hours notice** for cancellations. You can cancel through your dashboard or by asking me to cancel your appointment.',
        'arrival'        => '🕐 **Arrival:** Please arrive **10–15 minutes early** to settle in and prepare for your treatment. Late arrivals may result in a shortened session.',
        'payment'        => '💳 **Payment Methods:** We accept **Cash, Card, and GCash**. You may pay on arrival or at the time of booking.',
        'rescheduling'   => '🔄 **Rescheduling:** You can reschedule appointments up to **24 hours** before your scheduled time through our website or by asking me!',
        'advance_booking'=> '📅 **Advance Booking:** You can book appointments up to **60 days** in advance.',
        'no_show'        => '❗ **No-Show Policy:** Repeated no-shows may affect future booking privileges.'
    ],

    'faqs' => [
        [
            'keywords' => ['parking', 'car', 'vehicle', 'park'],
            'answer'   => '🚗 Yes! **Free parking** is available for clients at the Casa Erin Building. Just let reception know you are a client.'
        ],
        [
            'keywords' => ['first time', 'new client', 'first visit', 'new here', 'beginner'],
            'answer'   => '🌟 Welcome! For your **first visit**, we recommend arriving 15 minutes early for a brief consultation. Our specialists will guide you through the best services for your needs.'
        ],
        [
            'keywords' => ['walk-in', 'walk in', 'without appointment', 'drop in'],
            'answer'   => 'While we prefer bookings, we accept **walk-ins** if a slot is available. We recommend booking at least 2 days in advance during peak times to guarantee your slot.'
        ],
        [
            'keywords' => ['gift certificate', 'voucher', 'promo', 'gift card', 'gift'],
            'answer'   => '🎁 Yes! We offer **seasonal promos and packages**. Check our "Special Packages" section on the home page or ask me for current offers!'
        ],
        [
            'keywords' => ['peak', 'surcharge', 'extra', 'fee', 'additional charge'],
            'answer'   => '⏰ During **peak hours (11:00 AM – 2:00 PM)**, a small surcharge may apply to help manage high demand and maintain service quality.'
        ],
        [
            'keywords' => ['children', 'kids', 'child', 'minor'],
            'answer'   => '👶 We welcome clients of all ages! For minors under 18, a parent or guardian must be present during the appointment.'
        ],
        [
            'keywords' => ['product', 'buy', 'sell', 'purchase', 'retail'],
            'answer'   => '🛍️ Yes, we carry a curated selection of professional beauty and skincare products at our studio. Ask our staff for recommendations during your visit!'
        ],
        [
            'keywords' => ['group', 'group booking', 'event', 'party', 'bridal'],
            'answer'   => '🎊 We love group bookings! For groups of 4 or more, please contact us directly at **09171086478** to arrange a dedicated session and possible group packages.'
        ],
        [
            'keywords' => ['contact', 'phone', 'call', 'number', 'reach'],
            'answer'   => '📞 You can reach us at **09171086478** or email us at **info@beauteaesthetic.com**. We\'re also on Facebook: [Honey\'s Beauty Lounge](https://www.facebook.com/HoneysBeautyLounge.ph)'
        ]
    ],

    'help' => [
        'general' => "❓ **How can I help you?**\n\nI can assist you with:\n• **Booking** new appointments\n• **Checking** your scheduled bookings\n• **Rescheduling** or **Cancelling** appointments\n• Answering questions about our **services**, **location**, and **policies**\n\nJust type what you're looking for, or use the menu buttons below!",
    ],

    'feedback' => [
        'info' => "⭐ **We value your feedback!**\n\nAfter your session, you can rate our service through your dashboard. You can also leave a review on our [Facebook Page](https://www.facebook.com/HoneysBeautyLounge.ph) or send us a message directly!",
    ],

    'categories' => [
        'nails'        => '💅 **Nail Services** — Professional nail care, manicures, pedicures, and nail art for every style.',
        'eyebrows'     => '🪮 **Eyebrow Services** — Precision eyebrow shaping, threading, and grooming for the perfect frame.',
        'lashes'       => '👁️ **Lash Services** — Premium eyelash extensions and lifts for stunning, expressive eyes.',
        'wax'          => '✨ **Waxing Services** — Gentle and effective hair removal for smooth, lasting results.',
        'massages'     => '💆 **Massage Services** — Therapeutic body massages designed for deep relaxation and stress relief.',
        'facial'       => '🧖 **Facial Treatments** — Rejuvenating facials tailored to your skin\'s unique needs.',
        'skin_slimming'=> '⚡ **Skin & Slimming** — Advanced skin rejuvenation and body contouring procedures.'
    ],

    'packages' => [
        'Beauty Combo'    => 'Manicure + Pedicure — perfect for a polished, put-together look!',
        'Lash & Relax'    => 'Lash Extensions + Aromatherapy Massage — the ultimate treat.',
        'Ultimate Spa Day'=> 'Our full-day luxury pamper package. Ask staff for current pricing and availability.'
    ]
];
