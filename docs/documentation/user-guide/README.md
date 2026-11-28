# স্কুল ম্যানেজমেন্ট সিস্টেম — ব্যবহারকারী নির্দেশিকা

এই নির্দেশিকায় স্কুল ম্যানেজমেন্ট সিস্টেমের প্রতিটা ফিচার কীভাবে ব্যবহার করতে হয়, তা ধাপে ধাপে লেখা আছে। স্কুলের অ্যাডমিন, অফিস স্টাফ, শিক্ষক ও শিক্ষার্থী — সবার জন্য আলাদা অংশ রাখা হয়েছে।

---

## সূচিপত্র

### প্রথম অংশ — শুরু করা

| ক্রম | বিষয় | ফাইল |
|---|---|---|
| ০ | সিস্টেম পরিচিতি, লগইন, অ্যাপ ইনস্টল, ভাষা পরিবর্তন | [00-getting-started.md](00-getting-started.md) |

### দ্বিতীয় অংশ — প্যানেল অনুযায়ী নির্দেশিকা

| ক্রম | প্যানেল | কার জন্য | ফাইল |
|---|---|---|---|
| ক | অ্যাডমিন প্যানেল | সুপার অ্যাডমিন, অ্যাডমিন, স্টাফ | [panels/admin-panel.md](panels/admin-panel.md) |
| খ | শিক্ষক প্যানেল | শিক্ষক | [panels/teacher-panel.md](panels/teacher-panel.md) |
| গ | শিক্ষার্থী প্যানেল | শিক্ষার্থী ও অভিভাবক | [panels/student-panel.md](panels/student-panel.md) |

### তৃতীয় অংশ — ফিচার অনুযায়ী বিস্তারিত নির্দেশিকা

| ক্রম | ফিচার | মূল কাজ | ফাইল |
|---|---|---|---|
| ১ | Academic Structure | ক্লাস, সেকশন, বিষয়, পরীক্ষা, মার্কস, রেজাল্ট | [features/01-academic-structure.md](features/01-academic-structure.md) |
| ২ | Attendance | শিক্ষার্থী, শিক্ষক ও স্টাফের উপস্থিতি | [features/02-attendance.md](features/02-attendance.md) |
| ৩ | User Profile | শিক্ষার্থী, শিক্ষক ও স্টাফের প্রোফাইল, প্রমোশন | [features/03-user-profile.md](features/03-user-profile.md) |
| ৪ | Fee & Finance | ফি, ছাড়, ইনভয়েস, ফি আদায়, স্কুল অ্যাকাউন্ট | [features/04-fee-and-finance.md](features/04-fee-and-finance.md) |
| ৫ | Communication | নোটিশ ও নোটিফিকেশন | [features/05-communication.md](features/05-communication.md) |
| ৬ | SMS | SMS সেটিংস, ব্যালেন্স, রিচার্জ, SMS লগ | [features/06-sms.md](features/06-sms.md) |
| ৭ | Document Management | অ্যাডমিট কার্ড, মার্কশিট, আইডি কার্ড, সিট প্ল্যান | [features/07-document-management.md](features/07-document-management.md) |
| ৮ | HR & Staff Management | ছুটি, ছুটির আবেদন, ভারপ্রাপ্ত অ্যাডমিন, সরকারি ছুটি | [features/08-hr-and-staff.md](features/08-hr-and-staff.md) |
| ৯ | Salary Management | বেতনের উপাদান, কাঠামো, ইনভয়েস, বেতন প্রদান | [features/09-salary-management.md](features/09-salary-management.md) |
| ১০ | Fund Management | আয়-ব্যয়ের খাত, ফান্ড লেনদেন, অ্যাকাউন্ট লেজার | [features/10-fund-management.md](features/10-fund-management.md) |
| ১১ | User Management | রোল ও পারমিশন | [features/11-user-management.md](features/11-user-management.md) |
| ১২ | Activity Log | কে কখন কী পরিবর্তন করেছে | [features/12-activity-log.md](features/12-activity-log.md) |
| ১৩ | School Settings | স্কুলের তথ্য, গ্রেডিং স্কেল, প্রশ্নব্যাংক সংযোগ | [features/13-school-settings.md](features/13-school-settings.md) |

---

## এই নির্দেশিকা কীভাবে পড়বেন

- **নতুন ব্যবহারকারী:** প্রথমে [00-getting-started.md](00-getting-started.md) পড়ুন, তারপর নিজের প্যানেলের ফাইল পড়ুন।
- **শিক্ষক:** [শিক্ষক প্যানেল](panels/teacher-panel.md) ফাইলটিই যথেষ্ট।
- **শিক্ষার্থী বা অভিভাবক:** [শিক্ষার্থী প্যানেল](panels/student-panel.md) ফাইলটিই যথেষ্ট।
- **অ্যাডমিন বা অফিস স্টাফ:** [অ্যাডমিন প্যানেল](panels/admin-panel.md) পড়ার পর যে কাজ করবেন সেই ফিচারের ফাইল পড়ুন।

## নির্দেশিকায় ব্যবহৃত চিহ্ন

| চিহ্ন | অর্থ |
|---|---|
| **মেনু:** | বাম পাশের মেনুতে কোথায় ক্লিক করতে হবে |
| **বাটন** | স্ক্রিনে যে বাটনে ক্লিক করতে হবে, সেটি মোটা অক্ষরে লেখা |
| **সতর্কতা:** | ভুল করলে সমস্যা হতে পারে, এমন বিষয় |
| **টিপস:** | কাজ সহজ করার পরামর্শ |
| **মনে রাখুন:** | গুরুত্বপূর্ণ নিয়ম |
| `[স্ক্রিনশট: ...]` | এখানে স্ক্রিনের ছবি বসানো হবে |

## মেনু ও বাটনের নাম সম্পর্কে

সিস্টেমের মেনু ও বাটনের নাম স্ক্রিনে সাধারণত ইংরেজিতে দেখায়। তাই এই নির্দেশিকায় মেনুর নাম স্ক্রিনে যেভাবে লেখা থাকে ঠিক সেভাবেই ইংরেজিতে দেওয়া হয়েছে, যাতে খুঁজে পেতে সুবিধা হয়। যেমন — **মেনু: Fee & Finance → Fee Payments**।

## PDF তৈরি করার জন্য পরামর্শ

এই ফোল্ডারের প্রতিটা ফাইল আলাদা PDF হিসেবে, অথবা সবগুলো মিলিয়ে একটি PDF হিসেবে বানানো যায়।

- শিক্ষকদের জন্য: `00-getting-started.md` + `panels/teacher-panel.md`
- শিক্ষার্থীদের জন্য: `00-getting-started.md` + `panels/student-panel.md`
- অ্যাডমিনদের জন্য: সম্পূর্ণ ফোল্ডার
