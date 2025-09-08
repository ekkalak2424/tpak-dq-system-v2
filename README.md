# TPAK DQ System - WordPress Plugin

ระบบจัดการข้อมูลคุณภาพสำหรับ TPAK Survey System - เชื่อมต่อกับ LimeSurvey API และจัดการกระบวนการตรวจสอบ 3 ขั้นตอน

## คุณสมบัติหลัก

### 🔗 การเชื่อมต่อ LimeSurvey API
- เชื่อมต่อกับ LimeSurvey RemoteControl 2 API
- ดึงข้อมูลแบบสอบถามอัตโนมัติ
- ป้องกันการนำเข้าข้อมูลซ้ำ

### 👥 ระบบผู้ใช้งาน 4 ระดับ
- **Administrator**: จัดการการตั้งค่าและควบคุมระบบ
- **Interviewer (A)**: ตรวจสอบและแก้ไขข้อมูลขั้นแรก
- **Supervisor (B)**: ตรวจสอบและยืนยันข้อมูล
- **Examiner (C)**: อนุมัติขั้นสุดท้าย

### 🔄 กระบวนการตรวจสอบ 3 ขั้นตอน
1. **Interviewer (A)**: ตรวจสอบและแก้ไขข้อมูล > ส่งต่อให้ Supervisor
2. **Supervisor (B)**: ตรวจสอบข้อมูล > Sampling Gate (70% เสร็จสมบูรณ์, 30% ส่งต่อให้ Examiner)
3. **Examiner (C)**: อนุมัติขั้นสุดท้าย

### 📊 Dashboard และการแสดงผล
- แสดงสถิติข้อมูลตามสถานะ
- ฟิลเตอร์ตามสิทธิ์ผู้ใช้
- ประวัติการตรวจสอบ (Audit Trail)

### 📧 ระบบแจ้งเตือน
- แจ้งเตือนผ่านอีเมลเมื่อมีงานใหม่
- แจ้งเตือนเมื่อมีการเปลี่ยนสถานะ

## การติดตั้ง

### ความต้องการของระบบ
- WordPress 5.0 หรือใหม่กว่า
- PHP 7.4 หรือใหม่กว่า
- MySQL 5.7 หรือใหม่กว่า

### ขั้นตอนการติดตั้ง
1. อัปโหลดโฟลเดอร์ `tpak-dq-system` ไปยัง `/wp-content/plugins/`
2. เปิดใช้งานปลั๊กอินผ่าน WordPress Admin
3. ไปที่ **TPAK DQ System > Settings** เพื่อตั้งค่า API
4. สร้างผู้ใช้งานและกำหนด Role ตามต้องการ

## การตั้งค่า

### 1. การตั้งค่า LimeSurvey API
ไปที่ **TPAK DQ System > Settings** และกรอกข้อมูล:
- **LimeSurvey URL**: URL ของ LimeSurvey installation
- **Username**: ชื่อผู้ใช้ LimeSurvey
- **Password**: รหัสผ่าน LimeSurvey
- **Survey ID**: ID ของแบบสอบถามที่ต้องการนำเข้า

### 2. การตั้งค่า Cron Job
- **Import Interval**: ความถี่ในการดึงข้อมูล (ทุกชั่วโมง, วันละ 2 ครั้ง, วันละครั้ง, สัปดาห์ละครั้ง)
- **Survey ID**: ID ของแบบสอบถาม

### 3. การตั้งค่าการแจ้งเตือน
- **Email Notifications**: เปิด/ปิดการแจ้งเตือนอีเมล
- **Sampling Percentage**: เปอร์เซ็นต์การสุ่มตรวจสอบ (1-100)

## การใช้งาน

### สำหรับ Administrator
1. **Dashboard**: ดูสถิติและสถานะของข้อมูลทั้งหมด
2. **Settings**: ตั้งค่า API และการทำงานของระบบ
3. **Import Data**: นำเข้าข้อมูลด้วยตนเอง
4. **Manage Users**: จัดการผู้ใช้งานและสิทธิ์

### สำหรับ Interviewer (A)
1. เข้าไปที่ **ชุดข้อมูลตรวจสอบ** ในเมนูหลัก
2. ดูรายการข้อมูลที่มีสถานะ "รอการตรวจสอบ A" หรือ "ส่งกลับจาก B"
3. คลิกเข้าไปดูรายละเอียดและแก้ไขข้อมูล
4. กด "ยืนยันและส่งต่อให้ Supervisor"

### สำหรับ Supervisor (B)
1. เข้าไปที่ **ชุดข้อมูลตรวจสอบ**
2. ดูรายการข้อมูลที่มีสถานะ "รอการตรวจสอบ B"
3. คลิกเข้าไปดูรายละเอียด (อ่านอย่างเดียว)
4. กด "ยืนยันข้อมูล" หรือ "ส่งกลับเพื่อแก้ไข"

### สำหรับ Examiner (C)
1. เข้าไปที่ **ชุดข้อมูลตรวจสอบ**
2. ดูรายการข้อมูลที่มีสถานะ "รอการตรวจสอบ C"
3. คลิกเข้าไปดูรายละเอียด (อ่านอย่างเดียว)
4. กด "อนุมัติขั้นสุดท้าย" หรือ "ส่งกลับเพื่อตรวจสอบอีกครั้ง"

## สถานะการตรวจสอบ

- **pending_a**: รอการตรวจสอบจาก Interviewer
- **pending_b**: รอการตรวจสอบจาก Supervisor
- **pending_c**: รอการตรวจสอบจาก Examiner
- **rejected_by_b**: ส่งกลับจาก Supervisor เพื่อแก้ไข
- **rejected_by_c**: ส่งกลับจาก Examiner เพื่อแก้ไข
- **finalized**: ตรวจสอบเสร็จสมบูรณ์
- **finalized_by_sampling**: เสร็จสมบูรณ์โดยการสุ่ม

## การพัฒนา

### โครงสร้างไฟล์
```
tpak-dq-system/
├── tpak-dq-system.php          # ไฟล์หลัก
├── uninstall.php               # สคริปต์ลบข้อมูล
├── includes/                   # คลาสหลัก
│   ├── class-post-types.php
│   ├── class-roles.php
│   ├── class-api-handler.php
│   ├── class-cron.php
│   ├── class-workflow.php
│   └── class-notifications.php
├── admin/                      # ส่วนจัดการ
│   ├── class-admin-menu.php
│   ├── class-meta-boxes.php
│   └── class-admin-columns.php
└── assets/                     # CSS/JS
    ├── css/admin-style.css
    └── js/admin-script.js
```

### การขยายฟีเจอร์
1. เพิ่มคลาสใหม่ในโฟลเดอร์ `includes/`
2. ลงทะเบียนคลาสใน `tpak-dq-system.php`
3. เพิ่มเมนูใหม่ใน `admin/class-admin-menu.php`

## ระบบตรวจสอบข้อมูล (Data Validation)

### คุณสมบัติการตรวจสอบข้อมูล
- **API Settings Validation**: ตรวจสอบ URL, Username, Password, Survey ID
- **Survey Data Validation**: ตรวจสอบโครงสร้างข้อมูลจาก LimeSurvey
- **Workflow Actions Validation**: ตรวจสอบสิทธิ์และข้อมูลในการดำเนินการ
- **Meta Box Data Validation**: ตรวจสอบ JSON format และขนาดข้อมูล
- **User Input Validation**: ตรวจสอบความยาว, รูปแบบ, และเนื้อหาที่ปลอดภัย

### ฟังก์ชันตรวจสอบหลัก
- `validate_email()`: ตรวจสอบรูปแบบอีเมล
- `validate_url()`: ตรวจสอบ URL และ endpoint ที่กำหนด
- `validate_numeric_id()`: ตรวจสอบ ID ตัวเลข
- `validate_percentage()`: ตรวจสอบเปอร์เซ็นต์ (1-100)
- `validate_text()`: ตรวจสอบข้อความและความยาว
- `validate_json()`: ตรวจสอบรูปแบบ JSON
- `validate_date()`: ตรวจสอบรูปแบบวันที่

### การทดสอบ Validation
เรียกใช้ไฟล์ `test_validation.php` เพื่อทดสอบฟังก์ชันตรวจสอบข้อมูลทั้งหมด

## การแก้ไขปัญหา

### ปัญหาที่พบบ่อย

**1. ไม่สามารถเชื่อมต่อ LimeSurvey API ได้**
- ตรวจสอบ URL, Username, และ Password
- ตรวจสอบว่า LimeSurvey RemoteControl 2 API เปิดใช้งาน
- ตรวจสอบการตั้งค่า Firewall

**2. ไม่ได้รับอีเมลแจ้งเตือน**
- ตรวจสอบการตั้งค่า SMTP ของ WordPress
- ตรวจสอบว่า Email Notifications เปิดใช้งาน
- ตรวจสอบ Spam Folder

**3. Cron Job ไม่ทำงาน**
- ตรวจสอบการตั้งค่า WP-Cron
- ตรวจสอบ Server Cron Jobs
- ตรวจสอบ Log Files

**4. ข้อผิดพลาดในการตรวจสอบข้อมูล**
- ตรวจสอบรูปแบบข้อมูลที่ป้อนเข้า
- ดู Error Messages ในหน้า Admin
- เรียกใช้ `test_validation.php` เพื่อทดสอบระบบ

## การสนับสนุน

สำหรับการสนับสนุนและรายงานปัญหา กรุณาติดต่อ:
- Email: support@tpak.org
- Website: https://tpak.org

## ใบอนุญาต

ปลั๊กอินนี้เผยแพร่ภายใต้ใบอนุญาต GPL v2 หรือใหม่กว่า

## เวอร์ชัน

- **เวอร์ชันปัจจุบัน**: 1.0.0
- **วันที่เผยแพร่**: 30 กรกฎาคม 2568
- **ผู้พัฒนา**: TPAK Development Team 