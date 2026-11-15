# CPMMIS — Cost and Benefit Analysis

**System:** Capstone Project Management and Monitoring Information System (CPMMIS)
**Technology:** PHP 8.x, MySQL/MariaDB, Tailwind CSS, Chart.js, Gmail SMTP
**Users:** Administrator, Adviser, Group Leader/Student, Panelist
**Scope costed:** accounts/OTP, adviser requests, group management, tasks, submissions/review, meetings, chat, notifications, letters, archive, reports, activity logs, backup/maintenance, responsive UI

> **One-page visual summary:** [PNG](cpmmis-cost-benefit-chart.png) · [SVG](cpmmis-cost-benefit-chart.svg) · [PDF](cpmmis-cost-benefit-chart.pdf) · [Generator](generate_cost_benefit_chart.php)

> All amounts are planning estimates in Philippine pesos (₱) at 2026 price levels. They are based on the project's 32-week, four-member, 1,640-person-hour feasibility study and on the actual modules present in the codebase. Adjust the rates and the number of groups to match your institution's actual data before final defense.

---

## 1. Costing assumptions

| Assumption | Value used |
|---|---|
| Development period | 8 months (32 weeks) |
| Team | 4 members: 1 project lead/analyst, 2 full-stack developers, 1 QA/documentation lead |
| Effort | 15 hours per member per week; 1,640 estimated person-hours |
| Development basis | Part-time (15/40 = 0.375 FTE) share of entry-level monthly salaries |
| Operational period | 12 months (Year 1) |
| Deployment scale for benefits | 100 capstone groups, 12 advisers, 2 admin staff |
| Currency | Philippine peso (₱) |
| Software licenses | ₱0 — all tools are free/open source (PHP, MariaDB, Laragon, VS Code, Git, GitHub Free, Tailwind CDN, Chart.js, Mermaid) |

---

## 2. Development cost (one-time)

### 2.1 Personnel

| Item / Quality | Qty | Unit | No. of Months | Monthly Cost | Total |
|---|---:|---|---:|---:|---:|
| Project Lead / Systems Analyst — BSIT/BSIS graduate; requirements, design, coordination, documentation | 1 | person | 8 | ₱9,000 | ₱72,000 |
| Full-Stack Developer — PHP 8/MySQL, front-end and back-end, security | 2 | persons | 8 | ₱9,000 each | ₱144,000 |
| QA / Documentation Lead — test plans, UAT, manuals, defense materials | 1 | person | 8 | ₱7,500 | ₱60,000 |
| **Personnel subtotal** | **4** | **persons** | **8** | **₱34,500** | **₱276,000** |

### 2.2 Equipment, tools, and project expenses

| Item / Quality | Qty | Unit | No. of Months | Monthly Cost | Total |
|---|---:|---|---:|---:|---:|
| Laptop — Core i5 12th gen or better, 16 GB RAM, 512 GB SSD (8/36-month depreciation of ₱45,000 units) | 4 | units | 8 | ₱5,000 | ₱40,000 |
| Internet/data — fiber or mobile data for development and testing | 4 | subscriptions | 8 | ₱2,000 | ₱16,000 |
| Electricity — development and testing | 4 | workstations | 8 | ₱2,000 | ₱16,000 |
| Staging hosting — shared/VPS for testing and UAT | 1 | account | 8 | ₱500 | ₱4,000 |
| Domain name — .ph or .com, one-year registration | 1 | registration | 8 | ₱125 | ₱1,000 |
| Software and tools — Laragon, PHP, MariaDB, VS Code, Git, GitHub Free, Tailwind, Chart.js | 1 | suite | 8 | ₱0 | ₱0 |
| Printing and binding — manuscript, user manual, forms | 1 | set | — | — | ₱8,000 |
| Cloud backup/USB — source code, database, manuscripts | 1 | set | — | — | ₱2,000 |
| UAT and coordination — transport, test accounts, tokens | 1 | lump sum | — | — | ₱4,000 |
| **Non-personnel subtotal** | | | | | **₱91,000** |
| Contingency — 10% of non-personnel | | | | | **₱9,100** |
| **Total non-personnel** | | | | | **₱100,100** |

### 2.3 Total development cost

| Component | Amount |
|---|---:|
| Personnel | ₱276,000 |
| Non-personnel (including 10% contingency) | ₱100,100 |
| **Total development cost** | **₱376,100** |
| Effective development cost per month | ₱47,013 |
| Effective cost per person-hour (1,640 hours) | ₱229 |

> **Note on equipment:** laptops were treated as existing assets and charged at depreciation. If your manuscript requires full purchase cost, add ₱140,000 (4 × ₱45,000 − ₱40,000 depreciation already charged), giving about **₱516,100**.
>
> **Note on schedule:** this table uses 8 months (32 weeks). If you follow the March–November 2026 seven-phase schedule instead, use 9 months: personnel becomes ₱310,500 and total development cost about **₱421,050**.

---

## 3. Operational cost (recurring, Year 1)

| Item / Quality | Qty | Unit | No. of Months | Monthly Cost | Total |
|---|---:|---|---:|---:|---:|
| Web hosting — 2 vCPU, 4 GB RAM, 50 GB SSD, daily backup, 99.9% uptime | 1 | account | 12 | ₱1,200 | ₱14,400 |
| Domain name — .ph or .com, annual renewal | 1 | registration | 12 | ₱100 | ₱1,200 |
| SSL certificate — Let's Encrypt, TLS 1.2/1.3 | 1 | certificate | 12 | ₱0 | ₱0 |
| Email/SMTP — Gmail app password (free tier) | 1 | account | 12 | ₱0 | ₱0 |
| Off-site backup — 100 GB encrypted cloud storage | 1 | account | 12 | ₱300 | ₱3,600 |
| Technical maintenance — security patches, bug fixes, database optimization, backup verification (8 hours/month × ₱350/hour) | 1 | service | 12 | ₱2,800 | ₱33,600 |
| Uptime monitoring — UptimeRobot free tier | 1 | service | 12 | ₱0 | ₱0 |
| Internet and electricity — administrator workstation allocation | 1 | allocation | 12 | ₱800 | ₱9,600 |
| User support and refresher training — help desk, orientation | 1 | service | 12 | ₱500 | ₱6,000 |
| **Subtotal** | | | | **₱5,700** | **₱68,400** |
| Contingency — 10% | | | | | **₱6,840** |
| **Total operational cost (Year 1)** | | | | **₱6,270** | **₱75,240** |

### Deployment alternatives

| Setup | Estimated annual operating cost |
|---|---:|
| Cloud/shared hosting (table above) | ₱75,240 |
| School-owned server, existing IT staff, free domain/subdomain | ₱35,000–₱45,000 |
| Commercial hosting + Google Workspace + managed support | ₱110,000–₱150,000 |

For planning, use **₱75,000 per year** as the base case.

---

## 4. Total cost of ownership

| Period | Development | Operations | Total cost |
|---|---:|---:|---:|
| Year 1 | ₱376,100 | ₱75,240 | **₱451,340** |
| Year 2 | — | ₱75,240 | **₱75,240** |
| Year 3 | — | ₱75,240 | **₱75,240** |
| **3-year total** | **₱376,100** | **₱225,720** | **₱601,820** |
| **5-year total** | **₱376,100** | **₱376,200** | **₱752,300** |

Average monthly cost over three years: **₱16,717**.

---

## 5. Benefits

### 5.1 Tangible, quantified benefits (annual, 100 groups)

| Benefit | Basis of estimate | Annual value |
|---|---|---:|
| Adviser time recovered | 12 advisers × 2 hours/week × 30 weeks = 720 hours × ₱200/hour | ₱144,000 |
| Administrative time recovered | 2 staff × 5 hours/week × 40 weeks = 400 hours × ₱150/hour | ₱60,000 |
| Group leader time recovered | 100 leaders × 1 hour/month × 8 months = 800 hours × ₱100/hour | ₱80,000 |
| Printing and photocopy avoided | 100 groups × 6 submissions × 2 copies × 20 pages × ₱1.50, plus forms and letters | ₱42,000 |
| Faster archive retrieval | 250 requests × 12 minutes saved × ₱150/hour | ₱7,500 |
| Avoided lost or reworked documents | Fewer misplaced files, duplicate prints, and repeated encoding | ₱10,000 |
| Meeting scheduling and coordination | 100 groups × 2 meetings × 0.25 hour × ₱150/hour | ₱7,500 |
| **Total estimated annual benefit** | | **₱351,000** |

### 5.2 Intangible benefits

| Area | Benefit |
|---|---|
| Single source of truth | One database for groups, advisers, tasks, submissions, meetings, letters, and archives; no conflicting spreadsheets |
| Real-time monitoring | Advisers and administrators see pending, approved, rejected, and overdue work immediately, enabling early intervention |
| Transparency and accountability | Role-based access, activity logs, login logs, and submission history make actions traceable |
| Communication | Built-in chat, notifications, and meeting decisions reduce missed messages and repeated follow-ups |
| Document security | Prepared statements, CSRF protection, login rate limiting, session versioning, and account suspension protect records |
| Accessibility | 24/7 access from any browser; responsive layout works on phones, tablets, and desktops |
| Paperless archive | Searchable, centralized repository reduces physical storage and document deterioration |
| Decision support | Reports and analytics on group progress, submission status, and adviser workload support planning and accreditation |
| Scalability and reuse | The same platform can serve other programs or campuses with minimal configuration |
| Institutional image | A modern, organized capstone monitoring process improves service quality and stakeholder confidence |

### 5.3 Quality attributes (ISO/IEC 25010 summary)

| Attribute | How CPMMIS addresses it |
|---|---|
| Functional suitability | Covers the complete advising-to-archive workflow for all four roles |
| Performance efficiency | Lightweight PHP/MySQL stack; AJAX chat polling and indexed queries; no heavy build pipeline |
| Compatibility | Runs in any modern browser; responsive at 320 px to 1440 px |
| Usability | Consistent dashboards, modals, filters, notifications, and mobile card tables |
| Reliability | Daily backups, session handling, deadline checks isolated from core requests, graceful database-error responses |
| Security | Prepared statements, CSRF tokens, password policy, OTP reset, rate limiting, role checks, audit logs |
| Maintainability | Modular `admin/`, `adviser/`, `group/`, and `backend/` structure; shared helpers and components |
| Portability | Standard PHP/MySQL stack; deployable on shared hosting, VPS, or school server |

---

## 6. Cost-benefit analysis

### 6.1 Base case (moderate benefits)

| Item | Year 1 | Year 2 | Year 3 |
|---|---:|---:|---:|
| Total cost | ₱451,340 | ₱75,240 | ₱75,240 |
| Total benefit | ₱351,000 | ₱351,000 | ₱351,000 |
| Net benefit | **−₱100,340** | **₱275,760** | **₱275,760** |
| Cumulative net | −₱100,340 | ₱175,420 | ₱451,180 |

| Indicator | Result |
|---|---:|
| Payback period | **1.36 years (about 16 months)** |
| 3-year ROI | **75.0%** |
| 3-year benefit-cost ratio | **1.75 : 1** |
| 5-year ROI | **133.3%** |
| 5-year benefit-cost ratio | **2.33 : 1** |

### 6.2 Sensitivity analysis

| Scenario | Annual benefit | Annual operating cost | Net per year after Year 1 | Payback | 3-year BCR |
|---|---:|---:|---:|---:|---:|
| Conservative (−25%) | ₱263,250 | ₱75,240 | ₱188,010 | 2.00 years | 1.31 : 1 |
| Base case | ₱351,000 | ₱75,240 | ₱275,760 | 1.36 years | 1.75 : 1 |
| Optimistic (+20%) | ₱421,200 | ₱75,240 | ₱345,960 | 1.09 years | 2.10 : 1 |

### 6.3 Cash-basis view (student labor donated)

Because the development labor is contributed by the student team, the institution's actual cash outlay is much lower than the full economic cost:

| Item | Amount |
|---|---:|
| Development cash outlay (non-personnel only) | ₱100,100 |
| Year 1 operating cost | ₱75,240 |
| **Year 1 cash cost** | **₱175,340** |
| Year 1 benefit | ₱351,000 |
| **Year 1 net cash benefit** | **₱175,660** |
| Cash payback period | **about 4 months of operation** |
| 3-year cash BCR | **3.23 : 1** |

This is the figure to cite when the panel asks what the school actually has to spend. The full economic cost (₱376,100 development) is the correct figure when comparing the system with a commercially developed alternative.

---

## 7. Conclusion

CPMMIS requires an estimated **₱376,100** to develop and **₱75,240 per year** to operate. Against an estimated **₱351,000 per year** in recovered staff/student time and avoided printing and document costs, the system breaks even in about **1.4 years** on a full economic-cost basis, or in **about 4 months** on the institution's actual cash basis. The three-year benefit-cost ratio is **1.75 : 1**, rising to **2.33 : 1** over five years.

The investment is justified not only by the quantified savings but also by the system's qualitative gains: a single source of truth, real-time monitoring, traceable submissions, secure records, 24/7 access, and a searchable archive. These benefits directly support the university's capstone supervision, accreditation, and records-management processes.

---

## 8. Quick facts for the manuscript

| Question | Answer |
|---|---|
| Development cost | **₱376,100** (8 months, 4-person team, 1,640 person-hours) |
| Monthly development cost | **₱47,013** |
| Operational cost | **₱75,240 per year** |
| Monthly operational cost | **₱6,270** |
| Total Year 1 cost | **₱451,340** |
| Annual benefit | **₱351,000** |
| Payback period | **1.36 years** (full cost) / **~4 months** (cash basis) |
| 3-year ROI | **75.0%** |
| 3-year BCR | **1.75 : 1** |
| Software license cost | **₱0** (open-source stack) |
| Development duration | **8 months** (32 weeks); 9 months if using the March–November 2026 schedule |
