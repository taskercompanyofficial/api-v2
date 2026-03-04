<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WhatsAppTemplate;

class WhatsAppTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'greetings_template',
                'language' => 'en',
                'category' => 'MARKETING',
                'status' => 'APPROVED',
                'whatsapp_template_id' => '900511552690688',
                'preview_text' => "Assalam u Alaikum {{customer_name}} !!\nI'm *{{staff_name}}* from Tasker Company.\n*{{custom_message}}* \nThanks.",
                'parameter_count' => 3,
                'components' => [
                    [
                        'type' => 'HEADER',
                        'format' => 'TEXT',
                        'text' => 'Assalam u Alaikum {{customer_name}} !!'
                    ],
                    [
                        'type' => 'BODY',
                        'text' => "I'm *{{staff_name}}* from Tasker Company.\n*{{custom_message}}* \nThanks."
                    ],
                    [
                        'type' => 'FOOTER',
                        'text' => 'Thank you for being a part of Tasker Company.'
                    ]
                ],
            ],
            [
                'name' => 'complaint_create_template',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
                'whatsapp_template_id' => '549050624802781',
                'preview_text' => "Dear {{1}}\nYour complaint has been registered successfully.\n\n*🆔 Complaint No*: {{1}}\n*📞 Phone*: {{2}}\n*🏠 Address*: {{3}}\n*⚠ Fault*: {{4}}\n\n*Our technical team will contact you within 24 hours.*",
                'parameter_count' => 5,
                'components' => [
                    [
                        'type' => 'HEADER',
                        'format' => 'TEXT',
                        'text' => 'Dear {{1}}'
                    ],
                    [
                        'type' => 'BODY',
                        'text' => "Your complaint has been registered successfully.\n\n*🆔 Complaint No*: {{1}}\n*📞 Phone*: {{2}}\n*🏠 Address*: {{3}}\n*⚠ Fault*: {{4}}\n\n*Our technical team will contact you within 24 hours.*"
                    ],
                    [
                        'type' => 'FOOTER',
                        'text' => 'For quick actions, choose one of the options below:'
                    ],
                    [
                        'type' => 'BUTTONS',
                        'buttons' => [
                            [
                                'type' => 'URL',
                                'text' => 'View details',
                                'url' => 'https://www.taskercompany.com/{{1}}'
                            ],
                            [
                                'type' => 'PHONE_NUMBER',
                                'text' => 'Call Us Now',
                                'phone_number' => '+923041112717'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'one_time_passcode',
                'language' => 'en_US',
                'category' => 'AUTHENTICATION',
                'status' => 'APPROVED',
                'whatsapp_template_id' => '1176256827355102',
                'preview_text' => "*{{1}}* is your verification code. For your security, do not share this code.\n\nIf you have any concerns or questions, contact us at {{2}}.",
                'parameter_count' => 2,
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => "*{{1}}* is your verification code. For your security, do not share this code.\n\nIf you have any concerns or questions, contact us at {{2}}."
                    ],
                    [
                        'type' => 'FOOTER',
                        'text' => 'Expires in 10 minutes.'
                    ],
                    [
                        'type' => 'BUTTONS',
                        'buttons' => [
                            [
                                'type' => 'URL',
                                'text' => 'Copy code',
                                'url' => 'https://www.whatsapp.com/otp/code/?otp_type=COPY_CODE&code_expiration_minutes=10&code=otp{{1}}'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        foreach ($templates as $template) {
            WhatsAppTemplate::updateOrCreate(
                ['name' => $template['name'], 'language' => $template['language']],
                $template
            );
        }
    }
}
