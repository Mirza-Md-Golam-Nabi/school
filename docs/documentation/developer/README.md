# School Management System — Developer Reference

This folder is the technical companion to [`../user-guide/`](../user-guide/README.md). The user guide is written for end users and is meant to be exported as PDF. This part is for developers: architecture, code locations, database tables, business rules and gotchas. It is **not** meant to be shared with schools.

Written in Bengali, with code identifiers in English.

---

## সূচিপত্র

| ক্রম | বিষয় | ফাইল |
|---|---|---|
| ০ | আর্কিটেকচার, সেটআপ, প্যানেল, অথেন্টিকেশন, রোল-পারমিশন, শিডিউলার, রাউট, API | [00-architecture.md](00-architecture.md) |
| ১ | Academic Structure | [features/01-academic-structure.md](features/01-academic-structure.md) |
| ২ | Attendance (ZKTeco ডিভাইস API সহ) | [features/02-attendance.md](features/02-attendance.md) |
| ৩ | User Profile ও Promotion | [features/03-user-profile.md](features/03-user-profile.md) |
| ৪ | Fee & Finance | [features/04-fee-and-finance.md](features/04-fee-and-finance.md) |
| ৫ | Communication ও Push Notification | [features/05-communication.md](features/05-communication.md) |
| ৬ | SMS ও Central API | [features/06-sms.md](features/06-sms.md) |
| ৭ | Document Management ও QR যাচাই | [features/07-document-management.md](features/07-document-management.md) |
| ৮ | HR & Staff Management | [features/08-hr-and-staff.md](features/08-hr-and-staff.md) |
| ৯ | Salary Management | [features/09-salary-management.md](features/09-salary-management.md) |
| ১০ | Fund Management | [features/10-fund-management.md](features/10-fund-management.md) |
| ১১ | User Management | [features/11-user-management.md](features/11-user-management.md) |
| ১২ | Activity Log | [features/12-activity-log.md](features/12-activity-log.md) |
| ১৩ | School Settings | [features/13-school-settings.md](features/13-school-settings.md) |
| ১৪ | তিন প্যানেলের কাঠামো (Dashboard, Widget, Scope) | [panels.md](panels.md) |

## প্রতিটা ফিচার ফাইলের কাঠামো

1. সংশ্লিষ্ট কোড ফাইল (Resource, Page, Action, Model, Controller, Command, Test)
2. Database টেবিল ও সম্পর্ক
3. Permission ও Authorization
4. Business rule ও হিসাবের নিয়ম
5. স্বয়ংক্রিয় কাজ (Scheduler, Observer, Job, Notification)
6. জানা সমস্যা, সতর্কতা ও ভবিষ্যৎ পরিকল্পনা

## অন্যান্য পুরনো নোট

`docs/` ফোল্ডারে আগের পরিকল্পনা ও রিভিউ নোট আছে, যেগুলো এই ডকুমেন্টেশনের অংশ নয়:

- `docs/college-session-plan.md`
- `docs/performance-fix-plan.md`, `docs/performance-review.md`, `docs/php-side-sorting-review.md`
- `docs/qr-code-plan.md`
