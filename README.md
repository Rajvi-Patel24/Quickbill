Quickbill – Smart Retail Billing System

Quickbill is a modern web-based Point of Sale (POS) system designed for retail stores.
It simulates a real-world billing counter, enabling staff to scan products, generate invoices, and allowing admins to monitor sales and profits in real time.

🚀 Features
👨‍💼 Staff (Cashier) Module

Staff login with store location

Barcode scanning using camera (QuaggaJS)

Manual barcode entry

Real-time cart management

Automatic calculation of:

Subtotal

GST

Discounts

Final payable amount

Supports UPI & Cash payments

Printable invoice generation

🧾 Billing System

Clean, professional receipt layout

Includes store, date, items, quantity, tax & total

Print-ready invoice (thermal-style)

📊 Admin Dashboard

Secure admin login

City-wise sales filtering

Live sales monitoring

Key metrics:

Total revenue

Net profit

Products sold

Number of customers

Visual charts (Chart.js):

City-wise revenue vs profit

Category-wise sales analysis

Best-selling products tracking

🎨 UI & UX

Fully responsive design (mobile & desktop)

Light / Dark mode

Modern UI using Tailwind CSS

Smooth animations and transitions

🤖 AI Helper (Demo Feature)

Simulated AI assistant for:

Return policies

Exchange rules

Customer guidance

🛠 Tech Stack

Frontend

HTML5

Tailwind CSS

JavaScript

Alpine.js

Chart.js

QuaggaJS (Barcode Scanner)

Backend

PHP (Session-based logic)

Tools

XAMPP (Apache Server)

Browser Camera API

📂 Project Structure
luminous-pos/
│
├── quickbill.php        # Main POS application
├── README.md            # Project documentation
└── assets/              # (Optional) Images, icons, etc.

▶️ How to Run the Project

Install XAMPP

Start Apache

Copy the project folder to:

C:\xampp\htdocs\


Open browser and visit:

http://localhost/luminious_php/quickbill.php

🔐 Login Credentials
Staff Login

Any staff name

Any terminal ID

Select store city

Admin Login
Username: admin
Password: admin123

🔮 Future Enhancements

Product & inventory management

GST-compliant invoice numbering

PDF invoice export

Sales reports (daily/monthly)

Cloud deployment

👥 Team Members
Nemi Mody	Team Lead	Billing System barcode scanning,UI/UX
Shiv Patel	Backend logic, Tailwind Design
Yuval Patel	Analyst	Admin Dashboard & Charts
Rajvi Patel	Tester	Testing & Documentation
