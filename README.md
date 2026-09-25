CHAMA

Simple digital Chama management for groups, students, and communities.

CHAMA helps a group track members, monthly contributions, M-Pesa transaction codes, and who has paid or not paid.

MVP

The first version focuses on only three things:

- Members — add members and phone numbers
- Contributions — record amount, month, and M-Pesa transaction code
- Dashboard — see who has paid, who has not paid, and total collected

No loans, fines, meetings, or complicated financial features in the MVP.

How It Works

Member
   ↓
Pays via M-Pesa
   ↓
Contribution recorded
   ↓
M-Pesa code entered
   ↓
Dashboard updated
   ↓
Paid / Not Paid

Project Structure

CHAMA/
├── backend/
│   ├── public/
│   │   └── index.php
│   ├── src/
│   │   └── Config/
│   │       └── Database.php
│   └── database/
│       └── schema.sql
│
└── frontend/
    ├── index.html
    ├── members.html
    └── contribute.html

Technology

Frontend

- HTML5
- CSS
- JavaScript
- GitHub Pages

Backend

- PHP 8.2+
- PDO
- PostgreSQL

API

Get Members

GET /api/members

Returns all registered Chama members.

Add Member

POST /api/members

Example:

{
    "name": "Brian Mwangi",
    "phone": "0712345678"
}

Record Contribution

POST /api/contribute

Example:

{
    "member_id": 1,
    "amount": 3500,
    "mpesa_code": "QWE123XYZ",
    "month": "2026-09"
}

Monthly Report

GET /api/report?month=2026-09

Returns:

- Members
- Paid members
- Unpaid members
- Total collected

Data Model

The MVP uses two PostgreSQL tables:

members
   │
   └── contributions

Members

Stores:

- Member name
- Phone number
- Registration date

Contributions

Stores:

- Member
- Amount
- Month
- M-Pesa transaction code
- Date recorded

Deployment

The frontend is designed to run on GitHub Pages.

The PHP API must run on a PHP-compatible server with PostgreSQL.

GitHub Pages
     │
     │ HTTPS
     ▼
PHP 8.2 API
     │
     │ PDO
     ▼
PostgreSQL

GitHub Pages does not execute PHP, so the backend and database are deployed separately.

MVP Contribution Model

The initial Chama model is:

Monthly contribution: KSh 3,500

KSh 3,000 → Rotational payout
KSh   500 → Chama savings

For 10 members:

10 × KSh 3,500 = KSh 35,000/month

KSh 30,000 → rotational payout
KSh  5,000 → savings reserve

The MVP records contributions only. Rotational payouts and savings management can be added after the core product is proven.

Security

The backend should:

- Use PDO prepared statements
- Never store database credentials in Git
- Keep ".env" out of the repository
- Validate member and contribution data
- Validate M-Pesa transaction codes
- Use HTTPS in production
- Never trust financial values supplied by the frontend

Project Goal

Build a lightweight, practical Chama tool that a real group can use from a phone without unnecessary complexity.

CHAMA — Save Together. Grow Together.

---

Built by ThinkPlus
