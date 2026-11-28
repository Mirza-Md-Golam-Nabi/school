# ক. অ্যাডমিন প্যানেল — নির্দেশিকা

**কার জন্য:** সুপার অ্যাডমিন, অ্যাডমিন, অফিস স্টাফ ও ভারপ্রাপ্ত অ্যাডমিন।

## সূচিপত্র

- [ক.১ লগইন](#ক১-লগইন)
- [ক.২ কে কী দেখতে পান](#ক২-কে-কী-দেখতে-পান)
- [ক.৩ ড্যাশবোর্ড](#ক৩-ড্যাশবোর্ড)
- [ক.৪ বাম পাশের মেনু — সম্পূর্ণ তালিকা](#ক৪-বাম-পাশের-মেনু--সম্পূর্ণ-তালিকা)
- [ক.৫ নতুন স্কুল সেটআপের চেকলিস্ট](#ক৫-নতুন-স্কুল-সেটআপের-চেকলিস্ট)
- [ক.৬ দৈনন্দিন কাজ](#ক৬-দৈনন্দিন-কাজ)
- [ক.৭ মাসিক কাজ](#ক৭-মাসিক-কাজ)
- [ক.৮ পরীক্ষার মৌসুমের কাজ](#ক৮-পরীক্ষার-মৌসুমের-কাজ)
- [ক.৯ বছর শেষের কাজ](#ক৯-বছর-শেষের-কাজ)
- [ক.১০ সিস্টেম নিজে থেকে যা করে](#ক১০-সিস্টেম-নিজে-থেকে-যা-করে)

---

## ক.১ লগইন

- **ঠিকানা:** `আপনার-স্কুলের-ঠিকানা/admin`
- **লগইন:** ইমেইল ও পাসওয়ার্ড
- **পাসওয়ার্ড ভুলে গেলে:** লগইন পেজে **Forgot password?**
- **অ্যাপ ইনস্টল:** `আপনার-স্কুলের-ঠিকানা/admin/install`

বিস্তারিত: [শুরু করা](../00-getting-started.md)।

## ক.২ কে কী দেখতে পান

| ব্যবহারকারী | কী দেখেন |
|---|---|
| Super Admin | সব মেনু, সব কাজ |
| Admin | যে মেনুগুলোর অনুমতি "admin" রোলে দেওয়া আছে |
| Staff | যে মেনুগুলোর অনুমতি "staff" রোলে দেওয়া আছে |
| Acting Super Admin | School Settings সম্পাদনা ছাড়া প্রায় সব |
| Acting Admin | admin রোলের মতোই |

অনুমতি ঠিক করা হয় **User Management → Roles & Permissions** থেকে ([User Management](../features/11-user-management.md))।

## ক.৩ ড্যাশবোর্ড

লগইন করলে প্রথমে ড্যাশবোর্ড খোলে। এখানে এক নজরে স্কুলের অবস্থা দেখা যায়:

| অংশ | কী দেখায় |
|---|---|
| শিক্ষার্থীর আজকের উপস্থিতি | মোট শিক্ষার্থী, উপস্থিত, অনুপস্থিত |
| শিক্ষকের আজকের উপস্থিতি | শিক্ষকদের উপস্থিত ও অনুপস্থিত |
| স্টাফের আজকের উপস্থিতি | স্টাফদের উপস্থিত ও অনুপস্থিত |
| পরীক্ষার সারসংক্ষেপ | মোট পরীক্ষা, প্রকাশিত, অপ্রকাশিত |
| Top Attendance | সবচেয়ে বেশি উপস্থিত শিক্ষার্থী |
| Quick Actions | দ্রুত কাজের বাটন — **Create Exam**, **Result Publish**, **Marksheet**, **Admit Card**, **Manage Class** |
| সতর্কতা (Pending Alerts) | পরীক্ষা শেষ কিন্তু রেজাল্ট প্রকাশ হয়নি; ছুটির আবেদন অপেক্ষমাণ; বকেয়া ফি (কয়টি ইনভয়েস) |
| Finance Overview | Net Balance (সব অ্যাকাউন্টের ব্যালেন্স), Total Due Fee (মোট বকেয়া), Defaulted Fee |
| আয়-ব্যয়ের চার্ট | সময় অনুযায়ী Income ও Expense |

`[স্ক্রিনশট: অ্যাডমিন ড্যাশবোর্ড]`

## ক.৪ বাম পাশের মেনু — সম্পূর্ণ তালিকা

| গ্রুপ | মেনু | বিস্তারিত |
|---|---|---|
| Academic Structure | Subjects, Groups, Classes, Exam Types, Exams, Result Rules | [১. Academic Structure](../features/01-academic-structure.md) |
| Attendance | Student Attendance, Teacher Attendance, Staff Attendance, Attendance Ranking, Attendance Totals, Late Arrivals, Attendance Devices, Settings | [২. Attendance](../features/02-attendance.md) |
| User Profile | Students, Teachers, Staff, Promote | [৩. User Profile](../features/03-user-profile.md) |
| Fee & Finance | Fee Types, Fee Discounts, Student Discounts, School Accounts, Fee Structures, Fee Invoices, Fee Payments | [৪. Fee & Finance](../features/04-fee-and-finance.md) |
| Communication | Notices | [৫. Communication](../features/05-communication.md) |
| SMS | SMS Settings, SMS Vendors, Recharge Requests, Balance History, SMS Logs | [৬. SMS](../features/06-sms.md) |
| Document Management | Admit Cards, Marksheets, Seat Plan, Student ID Card, Teacher ID Card, Staff ID Card, Attendance Report | [৭. Document Management](../features/07-document-management.md) |
| HR & Staff Management | Leave Types, Leave Applications, Acting Admins, Public Holidays | [৮. HR & Staff](../features/08-hr-and-staff.md) |
| Salary Management | Salary Components, Salary Structures, Salary Invoices, Salary Payments, Bulk Payments | [৯. Salary](../features/09-salary-management.md) |
| Fund Management | Transaction Categories, Fund Transactions, Account Ledger | [১০. Fund Management](../features/10-fund-management.md) |
| User Management | Roles & Permissions | [১১. User Management](../features/11-user-management.md) |
| Activity Log | Activity Log | [১২. Activity Log](../features/12-activity-log.md) |
| School Settings | School Settings, Grading Scale, Question Bank | [১৩. School Settings](../features/13-school-settings.md) |
| — | Question Bank (সংযুক্ত থাকলে, নতুন ট্যাবে খোলে) | [১৩.৪](../features/13-school-settings.md#১৩৪-question-bank--প্রশ্নব্যাংক-সংযোগ) |

## ক.৫ নতুন স্কুল সেটআপের চেকলিস্ট

সুপার অ্যাডমিন হিসেবে এই ক্রমে কাজ করুন:

| ক্রম | কাজ | মেনু |
|---|---|---|
| ১ | নিজের পাসওয়ার্ড বদলান | উপরে ডানে নিজের নাম → প্রোফাইল |
| ২ | স্কুলের তথ্য, School Code, সাপ্তাহিক ছুটি, লোগো, স্বাক্ষর, সিল | School Settings → School Settings |
| ৩ | গ্রেডিং স্কেল দেখে নিন | School Settings → Grading Scale |
| ৪ | admin ও staff রোলে অনুমতি দিন | User Management → Roles & Permissions |
| ৫ | উপস্থিতির সময় ও দেরির সীমা | Attendance → Settings |
| ৬ | সরকারি ছুটির তালিকা | HR & Staff Management → Public Holidays |
| ৭ | বিষয়, গ্রুপ, ক্লাস, সেকশন, ক্লাসের বিষয় | Academic Structure |
| ৮ | শিক্ষক ও স্টাফ প্রোফাইল (Excel দিয়েও) | User Profile → Teachers / Staff |
| ৯ | ক্লাসে শ্রেণি শিক্ষক ও বিষয় শিক্ষক | Academic Structure → Classes → Edit |
| ১০ | শিক্ষার্থী ভর্তি (Excel দিয়েও) | User Profile → Students |
| ১১ | স্কুল অ্যাকাউন্ট, ফি-এর ধরন, ফি কাঠামো, ছাড় | Fee & Finance |
| ১২ | বেতনের অংশ ও প্রত্যেকের বেতন কাঠামো | Salary Management |
| ১৩ | ছুটির ধরন ও বরাদ্দ | HR & Staff Management → Leave Types |
| ১৪ | SMS চালু ও ঘটনা বেছে নেওয়া, রিচার্জ | SMS → SMS Settings |
| ১৫ | ফিঙ্গারপ্রিন্ট মেশিন (থাকলে) | Attendance → Attendance Devices |
| ১৬ | আইডি কার্ডের ডিজাইন | Document Management → ID Card |

## ক.৬ দৈনন্দিন কাজ

| কাজ | কোথায় |
|---|---|
| ড্যাশবোর্ডের সতর্কতা দেখা | Dashboard |
| শিক্ষার্থীর উপস্থিতি (মেশিন না থাকলে) | Attendance → Student Attendance |
| শিক্ষক ও স্টাফের উপস্থিতি | Attendance → Teacher / Staff Attendance |
| দেরিতে আসা দেখা | Attendance → Late Arrivals |
| ফি আদায় | Fee & Finance → Fee Payments |
| অন্যান্য খরচ এন্ট্রি | Fund Management → Fund Transactions |
| ছুটির আবেদন অনুমোদন | HR & Staff Management → Leave Applications |
| নোটিশ পাঠানো | Communication → Notices |

## ক.৭ মাসিক কাজ

| কাজ | কোথায় | নোট |
|---|---|---|
| মাসিক ফি ইনভয়েস | Fee & Finance → Fee Invoices | মাসের ১ তারিখে নিজে থেকে তৈরি হয় — ঠিকমতো হয়েছে কিনা দেখুন |
| বকেয়া দেখা ও SMS | Fee Invoices → ক্লাস | |
| বেতন ইনভয়েস | Salary Management → Salary Invoices | মাসের শেষ দিনে নিজে থেকে তৈরি হয় |
| বেতন প্রদান | Salary Management → Salary Payments → Payroll Batch Payment | |
| মাসিক উপস্থিতি রিপোর্ট | Document Management → Attendance Report | |
| SMS ব্যালেন্স দেখা | SMS → SMS Settings | |
| আয়-ব্যয় মেলানো | Fund Management → Account Ledger | |

## ক.৮ পরীক্ষার মৌসুমের কাজ

| ক্রম | কাজ | কোথায় |
|---|---|---|
| ১ | পরীক্ষা তৈরি | Academic Structure → Exams |
| ২ | বিষয় ও নম্বর | Exam → Subject Configs |
| ৩ | রুটিন | Exam → Exam Schedule |
| ৪ | এককালীন পরীক্ষা ফি | Fee Structures → Generate Invoices → One-Time |
| ৫ | অ্যাডমিট কার্ড ও সিট প্ল্যান | Document Management |
| ৬ | মার্কস এন্ট্রি (শিক্ষক বা অ্যাডমিন) | Exam → Subject Configs → Enter Marks |
| ৭ | উপস্থিতির মোট (দরকার হলে) | Attendance → Attendance Totals |
| ৮ | মেধাক্রম | Exam → Calculate Rankings |
| ৯ | ট্যাবুলেশন শিট ও রিপোর্ট দেখে যাচাই | Exam → Tabulation Sheet / Exam Report |
| ১০ | রেজাল্ট প্রকাশ | Exam → Published |
| ১১ | মার্কশিট | Document Management → Marksheets |
| ১২ | রেজাল্ট SMS / Push | Exam → Send Result |

## ক.৯ বছর শেষের কাজ

| ক্রম | কাজ | কোথায় |
|---|---|---|
| ১ | বার্ষিক পরীক্ষার রেজাল্ট ও মেধাক্রম | Exams |
| ২ | নতুন বছরের ফি কাঠামো | Fee Structures → Copy to New Session |
| ৩ | শিক্ষার্থীদের পরের ক্লাসে তোলা | User Profile → Promote |
| ৪ | নতুন বছরের শিক্ষক বরাদ্দ | Classes → Edit → Teachers |
| ৫ | নতুন বছরের ছাড় | Fee & Finance → Student Discounts |
| ৬ | নতুন বছরের সরকারি ছুটি | HR & Staff Management → Public Holidays |
| ৭ | বাৎসরিক আইডি কার্ড (প্রয়োজনে) | Document Management → Student ID Card |

## ক.১০ সিস্টেম নিজে থেকে যা করে

| কখন | কী |
|---|---|
| প্রতি মাসের ১ তারিখ সকাল ৭টা | মাসিক ফি ইনভয়েস তৈরি |
| প্রতি মাসের শেষ দিন রাত ১১টা | সেই মাসের বেতন ইনভয়েস তৈরি |
| প্রতি ঘণ্টা | স্কুল ছুটির পর মেশিনে যুক্ত কিন্তু না আসা ব্যক্তিদের Absent |
| প্রতিদিন সকাল ৮টা | ছুটি শেষে কাজে না ফেরা কর্মীদের হিসাব |
| প্রতিদিন রাত ১২:১০ | মেয়াদ শেষ হওয়া ভারপ্রাপ্ত অ্যাডমিন বন্ধ |
| প্রতি কয়েক মিনিট | পৌঁছায়নি এমন Push নোটিফিকেশন আবার পাঠানো |
| প্রতি রবিবার | ২ বছরের পুরনো Activity Log মুছে ফেলা |
| নির্ধারিত সময়ে | নির্ধারিত নোটিশ প্রকাশ |
