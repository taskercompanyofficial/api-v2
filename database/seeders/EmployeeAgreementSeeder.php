<?php

namespace Database\Seeders;

use App\Models\AgreementTemplate;
use App\Models\AgreementClause;
use Illuminate\Database\Seeder;

class EmployeeAgreementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the Employee Agreement Template
        $template = AgreementTemplate::create([
            'name' => 'Employee Agreement 2025-26',
            'purpose' => 'Standard employment agreement for Tasker Company staff members with terms, conditions, and policies.',
            'language' => 'mixed',
            'direction' => 'rtl',
            'is_active' => true,
            'version' => 1,
            'header_html' => $this->getHeaderHtml(),
            'footer_html' => $this->getFooterHtml(),
        ]);

        // Add all agreement clauses
        $this->addClauses($template->id);
    }

    private function getHeaderHtml(): string
    {
        return <<<'HTML'
<div class="agreement-header" style="text-align: center; margin-bottom: 30px;">
    <div style="text-align: right; font-size: 12px; margin-bottom: 20px;">
        <div><strong>TASKER</strong> <span style="font-size: 10px;">HVACR SOLUTION</span></div>
        <div>📞 +92 3023111000</div>
        <div>✉️ info@taskercompany.com</div>
        <div>📍 190/2-B Sabzi park, Lahore</div>
    </div>
    
    <h2 style="text-align: center; margin: 30px 0;">Agreement</h2>
    
    <div style="text-align: right; direction: rtl; margin-bottom: 20px;">
        <p>یہ معاہدہ Tasker Company (ٹاسکر کمپنی) اور</p>
        <p>{{employee_name}} کے درمیان 2025-26ء کے لیے طے پایا ہے۔</p>
    </div>
    
    <div style="text-align: left; margin-bottom: 20px;">
        <p><strong>Employee Information</strong></p>
        <p><strong>Name:</strong> {{employee_name}} <strong>CNIC:</strong> {{employee_cnic}} <strong>Cell No:</strong> {{employee_phone}}</p>
    </div>
    
    <div style="text-align: right; direction: rtl;">
        <p>CWS (کسٹمر ورک شیٹ)نگرانی کی ذمہ داریاں اورشفافیت کے ضمانت ہے۔</p>
    </div>
    
    <h3 style="text-align: center; margin: 20px 0;">Terms and Condition</h3>
</div>
HTML;
    }

    private function getFooterHtml(): string
    {
        return <<<'HTML'
<div class="agreement-footer" style="margin-top: 40px; page-break-inside: avoid;">
    <div style="margin-top: 30px;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <p><strong>Name:</strong> {{employee_name}}</p>
                    <p><strong>CNIC Number:</strong> {{employee_cnic}}</p>
                    <p><strong>Bank:</strong> {{bank_name}}</p>
                    <p><strong>Account Title:</strong> {{account_title}}</p>
                    <p><strong>Account Number:</strong> {{account_number}}</p>
                    <br><br>
                    <p><strong>Signature (Employee):</strong> __________________</p>
                    <p><strong>Date:</strong> {{current_date}}</p>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <br><br><br><br>
                    <p><strong>Signature (CEO):</strong> __________________</p>
                    <br><br>
                    <p><strong>General Manager:</strong> __________________</p>
                </td>
            </tr>
        </table>
    </div>
</div>
HTML;
    }

    private function addClauses(int $templateId): void
    {
        $clauses = [
            // Section: Main Terms
            ['number' => '1', 'content' => 'CWS (کسٹمر ورک شیٹ)ہررریقہ کار کی بنیاد اور ذمہ داری ہے۔', 'order' => 1],
            ['number' => '2', 'content' => 'کام شروع کرنے سے پہلے CWS تیار کریں۔', 'order' => 2],
            ['number' => '3', 'content' => 'Job Sheet کی ضرورت کے مطابق Remarks+Signature لینا لازمی ہے۔', 'order' => 3],
            ['number' => '4', 'content' => 'چھٹی لینے کی صورت (Allow)میں پہلے سےاطلاع دینا ضروری ہے۔', 'order' => 4],
            ['number' => '5', 'content' => 'اگر آپ کے CWS کی کوئی Complaints آئے تووہ Same Day میں Attend کریں۔ورنہ مزید تیار شدہ+Pending CWS (33)Status پر Freeze ہو جائیں گے۔', 'order' => 5],
            ['number' => '6', 'content' => 'یادرہے کہ سب کچھ CWS کے ذریعہ ہی ریکارڈ میں آتا ہے۔برائے مہربانی کسی بھی نوعیت کی شکایت بلا CWS کے نہیں۔', 'order' => 6],
            ['number' => '7', 'content' => 'آپ کو CWS کی خدمات میں بہتری لانے کے لیے ضروری تربیت اور وسائل فراہم کئے جائیں گے۔', 'order' => 7],
            ['number' => '8', 'content' => 'آگر CWS کام کے دوران Peroffice work operational میں آگئے تووہ کل کو Skip کرکے اگلے روزکرلیں۔', 'order' => 8],
            ['number' => '9', 'content' => 'نئے کسٹمر کو پہنچ کر کال کریں اورپہلے خود کا تعارف کروائیں۔', 'order' => 9],

            // Complaint Management
            ['number' => '10', 'content' => 'آگر کوئی بھی پچھلی Complaints کسٹمر دوباراہ کال کرے توبلا معاوضہ اس کو ٹھیک کرنا ہوگا۔صورت میں پچھلی سروس کی رقم واپس کرنی ہوگی۔', 'order' => 10],
            ['number' => '11', 'content' => 'پچھلی Complaints ++سے متعلق والی Appravel(آپروول)لینا لازمی ہے۔پھر انکے CWS ++کو Re-open کریں۔کام صاف ہوگا۔', 'order' => 11],
            ['number' => '12', 'content' => 'اگر کوئی Complaintنئی ہو اورپچھلی سروس سے متعلق ہوتووہ سروس کاری گارنٹی کے تحت مفت ہوگی۔', 'order' => 12],

            // Documentation & Reporting
            ['number' => '13', 'content' => 'تمام معلومات(Statement)کو100% کی درستگی کے ساتھ ریکارڈ کریں۔', 'order' => 13],
            ['number' => '14', 'content' => 'کمپنی کی پالیسی(Policy)کا احترام کریں۔', 'order' => 14],
            ['number' => '15', 'content' => 'اپنی CWS کی تمام پالیسی(Policy)کی پوری طرح پابندی کریں۔', 'order' => 15],
            ['number' => '16', 'content' => 'رپورٹیں وقت پر اور درست طریقے سے جمع کرانا ضروری ہے اگرکچھ سمجھ میں نہیں آتا توFeedback کرتے رہیں۔', 'order' => 16],
            ['number' => '17', 'content' => 'اگر کام کے دوران کوئی مانع صورت حال پیدا ہوتو(Save)ضروری پیش رفت کو محفوظ کریں۔', 'order' => 17],
            ['number' => '18', 'content' => 'کسٹمرز کی مہینے کی Save+نگرانی کرنا اور ان کے مسائل کی فوری حل کرنا لازمی ہے۔ہر CWS +مہینے میں Update ہونا چاہیے۔', 'order' => 18],
            ['number' => '19', 'content' => 'بروقت ڈیوٹی پر حاضری یقینی بنائیں اور تمام قوانین پر عمل کریں۔', 'order' => 19],
            ['number' => '20', 'content' => 'اگر آپ کام یاعملی میں کوئی غیرقانونی کام کریں گے تو اس کا ذمہ دار آپ خود ہونگے۔', 'order' => 20],

            // Customer Service
            ['number' => '21', 'content' => 'اگر کام میں آپ سے کوئی غلطی ہوئی ہوتو اسے فوری طورپر درست کریں۔', 'order' => 21],
            ['number' => '22', 'content' => 'کسٹمر کی ہر طرح کی سروس کو محفوظ اور درست طریقے سے مکمل کریں۔اچھے رویہ کے ساتھ فراہم کریں۔', 'order' => 22],
            ['number' => '23', 'content' => 'اگر کام پوراملنے کے روز جمع نہیں کروائیں توہرروز اضافی چارج لگائیں۔', 'order' => 23],
            ['number' => '24', 'content' => 'رپورٹیں اور Feedbackمعیاری طریقے سے تیار کریں اوراسے وقت پر جمع کروائیں۔ CWS (', 'order' => 24],
            ['number' => '25', 'content' => 'CWS میں Reporting Formatکو استعمال کرتے ہوئے تمام ملاقاتوں کو دستاویز کریں۔ CWS ++انتظامیہ نے طریقے کی جو Format تیار کی ہے اس بعینہ Reports تیاری لازمی ہے۔', 'order' => 25],

            // Report Types
            ['number' => 'Header', 'content' => 'Service Complaint Sheet 1', 'order' => 26, 'title' => null],
            ['number' => 'Header', 'content' => 'Daily Complaint Report  2', 'order' => 27, 'title' => null],
            ['number' => 'Header', 'content' => 'Required Data Report 3', 'order' => 28, 'title' => null],

            // Service Procedures
            ['number' => '26', 'content' => 'ہرروز(Job Sheet)کام شیٹ کا استعمال ضروری ہے۔یاد رہے کہ ورک شیٹ آپ کی محنت کووقار اور ضمانت دیتی ہے۔ہرCWS +کے لیے کسٹمر سے مخصوص Job Sheet پر دستخط لینا لازمی ہے۔', 'order' => 29],
            ['number' => '27', 'content' => 'اگر Job Sheet کے پیچھے Service Report(سروس رپورٹ)والا حصہ مکمل نہیں کریں گے توپھر آپ کو سروس کی تفصیلات کوCWS ++میں رجسٹر کرنا ہوگا۔', 'order' => 30],
            ['number' => '28', 'content' => 'CWS میںSummary(خلاصہ)لکھنا ضروری ہے۔ یہ آپ کی کارکردگی کی عکاسی کرے گا۔', 'order' => 31],
            ['number' => '29', 'content' => 'اگر CWS +کے لیے کوئی Direct(براہ راست)ہدایت دی جائے تو اس پر عمل درآمد لازمی ہے۔', 'order' => 32],

            // Complaints Records (continued)
            ['number' => '29', 'content' => 'بلاCWS +کے تیار کیے Complaints Record (کمپلینٹس ریکارڈ)میں درج نہیں ہوسکتی۔', 'order' => 33],
            ['number' => '30', 'content' => 'Complaints کی Attend کرنا لازمی ہے۔', 'order' => 34],
            ['number' => '31', 'content' => 'کسٹمر سے CWS +کمپلینٹ(Complaint)Attend کرنا لازمی ہے۔', 'order' => 35],
            ['number' => '32', 'content' => 'آبی CWS +میں Complaints ہو تو اس کا محتاط رویہ اختیار کرنا ضروری ہے۔', 'order' => 36],
            ['number' => '33', 'content' => 'CWS +کے ذریعے Status تبدیل کرنا اور Updated Data پر غورکرنا ضروری ہے۔ تاکہ Vendor 27کی کا رکردگی کی نگرانی بھی ہوسکے۔', 'order' => 37],
            ['number' => '', 'content' => 'Personalبرتاوکریں پرسپکٹ رکھیں۔', 'order' => 38],

            // Additional Policies
            ['number' => '34', 'content' => 'اگر کسی بھی معاملات میں کوئی تبدیلی پیش آتی ہے تویہ ہمیشہ مطلع رہیں۔', 'order' => 39],
            ['number' => '35', 'content' => 'آپری Complaints اگر آپ کےزریعے اورآگئے ہوں توانکو CWS +میں منتقل کر دیں۔', 'order' => 40],
            ['number' => '36', 'content' => 'کام کے دوران اگرآپ کوکوئی Field Complaints ک Attend(حاضری)کرنی ہوتو وہ ضرورکریں۔ آپ کی کارکردگی کا ثبوت ہوں گے۔', 'order' => 41],
            ['number' => '37', 'content' => 'نوکری آپ کو CWS +میں6سے3دن میں اوسطاً کم ازکم 90% مطابقت رکھنی ہوگی۔ورنہ CWS +کو نائگر کیاجاسکتا ہوگا۔', 'order' => 42],

            // Warranty Section
            ['number' => '', 'content' => 'Warranty Undertaking and Disclosure', 'order' => 43, 'title' => 'Warranty Undertaking and Disclosure', 'lang' => 'en', 'dir' => 'ltr'],
            ['number' => '38', 'content' => 'وارنٹی کے تحت فراہم کردہ تمام خدمات اور پرزجات کمپنی کی پالیسی کے مطابق ہوں گے۔ تمام وارنٹی کا موضوع کوعلانیہ اور صحیح طریقے سے کسٹمر کو آگاہ کرنا ضروری ہے۔', 'order' => 44],
            ['number' => '39', 'content' => 'وارنٹی کی شرائط کی واضح طور پر وضاحت کریں۔', 'order' => 45],
            ['number' => '39', 'content' => 'اگر وارنٹی کے تحت کسی پرزجات یا CWS +کی کوئی خدمت فراہم کی جائے تو DOP (Date of Purchase)یعنی خریداری کی تاریخ کو ضرور ریکارڈ میں شامل کریں۔', 'order' => 46],
            ['number' => '40', 'content' => 'تمام معلومات کو شفاف طریقےسےبیان کریں۔', 'order' => 47],

            // Accounts/Payments Section
            ['number' => '', 'content' => 'Accounts/Payments', 'order' => 48, 'title' => 'Accounts/Payments', 'lang' => 'en', 'dir' => 'ltr'],
            ['number' => '', 'content' => 'اگر CWS +میں کوئی رقم جمع کرنی ہو توبینک کوباخبر کریں۔', 'order' => 49],
            ['number' => '', 'content' => 'ہراکاؤنٹ کی رقم کو واضح طور پر ریکارڈ کریں اور معافی طلب کریں۔', 'order' => 50],
            ['number' => '', 'content' => 'آپ کی تنخواہ ہراتاہ پہلے ہفتہ میں جمع ہوجائے گی۔', 'order' => 51],
            ['number' => '', 'content' => 'Fiasal Bank, 3287787000003300, Tasker Company (فیصل بینک، ٹاسکر کمپنی) کے اکاؤنٹ میں رقم ڈالیں۔RS 5000', 'order' => 52],

            // Audit Section
            ['number' => '', 'content' => 'Audit', 'order' => 53, 'title' => 'Audit', 'lang' => 'en', 'dir' => 'ltr'],
            ['number' => '41', 'content' => 'آپ کی کارکردگی کو منتظم CWS +کے ذریعے چیک کیا جائے گا۔', 'order' => 54],
            ['number' => '42', 'content' => 'اگر کوئی تبدیلی درکار ہوئی یا آپ کو CWS +میں کچھ بہتری کرنی ہوگی تو Audit(آڈٹ)کرانا لازمی ہوگا۔', 'order' => 55],

            // CWS Management
            ['number' => '43', 'content' => 'کمپنی اورمختلف CWS +پر لازمی طورپرعملدرآمد کریں اوراپنے پیشے کے معزاز اورذمہ داری کوبراہ راست کہلانے کےاستعمال تسلیم کریں۔اگر عمل نہیں ہوگا توبے وفائی کافریضہ سمجھا جائے گا۔', 'order' => 56],
            ['number' => '44', 'content' => 'وسائل کا صحیح استعمال کریں۔ CWS +پر ہیں تو کوئی ضائع نہیں۔', 'order' => 57],

            // Parts Provision/Monthly Inventory
            ['number' => '', 'content' => 'Parts Provision/Monthly Inventory', 'order' => 58, 'title' => 'Parts Provision/Monthly Inventory', 'lang' => 'en', 'dir' => 'ltr'],
            ['number' => '45', 'content' => 'آپکی CWS +کے لیے فراہم کیے گئے محصولات یا سامان کی صحیح صورت میں رکھیں۔ابر CWS +کی ضرورت ہوتو پہلے سے درخواست کریں تاکہ فراہمی کا انتظام ممکن ہوسکے۔', 'order' => 59],
            ['number' => '46', 'content' => 'ہر مہینےکے آخرتک آپکے پاس موجودہ سامان کو Resign Net Accepted (رزائنڈنیٹ اکسیپٹڈ)بھرلیں اورآفس کو فراہم کریں۔اگر روزانہ سامان آگیا ہوتو بھی اسکو اضافہ کردیں۔', 'order' => 60],
            ['number' => '47', 'content' => 'کمپنی کی کسی بھی لاعلمی،غلط بیانی یایہ غلط کام کو رکھ لینے پر روکے کوکوئی بھی معافی نہیں ہوگی۔ڈیمیج یا Vendor (وینڈر)کی ضایع ہونے اورموصول ہونےاورحصول کیذمہداری کی شرائط اورمعاہدہ شروع سےکہ اگرکوئی چیز گِر گئی تو اسکی ذمہ داری آپ کے پرہوگی۔', 'order' => 61],
        ];

        foreach ($clauses as $index => $clause) {
            AgreementClause::create([
                'agreement_template_id' => $templateId,
                'clause_number' => $clause['number'] ?? '',
                'title' => $clause['title'] ?? null,
                'content' => $clause['content'],
                'language' => $clause['lang'] ?? 'ur',
                'direction' => $clause['dir'] ?? 'rtl',
                'display_order' => $clause['order'],
                'is_active' => true,
            ]);
        }
    }
}
