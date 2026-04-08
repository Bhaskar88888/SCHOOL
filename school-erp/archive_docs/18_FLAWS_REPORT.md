# School ERP Flaws Report

Based on the provided project audit and analysis, here are the **18 critical and high-priority flaws** that affect the School ERP website's functionality, security, UX, and code quality. 

## 🔴 Broken Functionality (6 Flaws)
1. **`ipKeyGenerator` Import Missing**: The import for `ipKeyGenerator` in `rateLimiter.js` does not exist in the current version of the library. This will crash the rate limiter.
2. **Canteen Item Availability Mismatch**: The AI chatbot queries `available: true`, but the `CanteenItem` database model uses the field `isAvailable`. This causes the chatbot to return an empty menu.
3. **Payroll Payslip Mismatch**: The PDF generation route reads `netSalary`, but the `Payroll` database model uses `netPay`. This results in broken or missing data on employee payslip PDFs.
4. **Missing Payment Mode in Canteen**: The `CanteenSale` model is missing a `paymentMode` field, meaning payment method data is lost on every transaction.
5. **Missing Dashboard Routes**: `ParentDashboard` and `ConductorPanel` exist as components but are not mapped in `App.jsx`, leaving Parents and Conductors with broken or generic dashboards.
6. **Flawed Bulk Import Duplicate Check**: The student bulk import tool does not deduplicate records within the uploaded batch, leading to partial silent import failures.

## 🔒 Security Holes (5 Flaws)
7. **Unprotected Export Routes**: Data export APIs have no role verification, allowing any student to download the entire school database.
8. **Unprotected Remarks Access**: The route `GET /remarks/student/:id` lacks scope checking, allowing anyone to read the private remarks of any student.
9. **Demo Credentials Exposed**: The Login page displays administrative demo credentials in plaintext, posing a severe unauthorized access risk.
10. **Insecure JWT Storage**: Authentication tokens are stored in `localStorage`, which is highly vulnerable to XSS (Cross-Site Scripting) attacks.
11. **Notifications Unconfigured**: Critical infrastructure like SMS (Twilio) and Email (SMTP) lack environment credentials, completely breaking parent and staff notifications.

## ⚠️ UX & Navigation Problems (6 Flaws)
12. **Missing Page Layouts**: Over 9 frontend pages are missing the `<Layout>` wrapper, meaning they render without a continuous Sidebar or Navbar, creating a broken navigation experience.
13. **Zero Pagination**: List pages (e.g., student lists, fee history, ledgers) attempt to render the entire database at once. They will freeze browsers when handling real-world datasets of 1000+ records.
14. **Silent Error Swallowing**: Almost all frontend pages contain empty `catch(err) {}` blocks, meaning failing requests fail silently without warning the user.
15. **No Loading States**: Core data tables lack loading spinners, showing users a confusing blank screen while fetching data from the backend.
16. **Role Menu Leaking**: The Sidebar shows all application menu items to all roles. Students can mistakenly click on HR, Payroll, or Accounts menus before being blocked.
17. **No Form Validation**: Forms lack basic Regex validation, freely accepting invalid phone numbers, emails, and Aadhaar numbers.

## ⚙️ Code Quality & Performance (1 Flaw)
18. **Severe Dead Code & Missing Indexes**: The app contains heavy unused dependencies (`html2canvas`, `joi`), 4 completely unmounted middleware files, and lacks basic database indexing, leading to performance bottlenecks as data scales.
