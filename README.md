# OXU MED DENTAL — tayyor sayt (frontend + backend bitta joyda)

Bu papka frontend build qilingan holda (React -> static HTML/JS/CSS) va backend (PHP API)
bitta joyga birlashtirilgan holda beriladi. Hech qanday npm run build, .env, Vite proxy va
CORS sozlash SHART EMAS - hammasi bitta domenda ishlaydi.

## O'rnatish (3 qadam)

1. database.sql ni phpMyAdmin orqali import qiling (agar hali qilmagan bo'lsangiz).
   Boshlang'ich login: admin@oxumed.uz / admin123

2. Shu papkaning ICHIDAGI HAMMA NARSANI (index.html, assets/, api/, config/,
   helpers/, tools/, uploads/) OSPanel domeningizning ildiziga ko'chiring - masalan:
   C:\OSPanel\domains\oxumeddentalbackend\
   Eski fayllarning barchasini almashtiring (o'chirib, shularni qo'ying).

3. config/db.php da baza ulanish ma'lumotlarini tekshiring (host/user/parol) - odatda
   OSPanel'da standart root / bo'sh parol bilan ishlaydi, o'zgartirish shart emas.

Shu bo'ldi. Brauzerda domeningizni oching:
http://oxumeddentalbackend/

## Agar sahifa ochilib, lekin dizayn/JS ishlamasa

Bu odatda eski keshdan bo'ladi - Ctrl+Shift+R (hard refresh) qiling.

## Agar /login, /admin kabi sahifani to'g'ridan-to'g'ri (yangilab) ochganda 404 chiqsa

Bu server (agar Nginx bo'lsa) React Router uchun "fallback" sozlanmagani sababli. Ammo odatiy
foydalanishda (saytda tugmalar orqali yurish) bu muammo bo'lmaydi, chunki React ilova sahifa
ichida o'zi navigatsiya qiladi. Agar baribir to'g'ridan-to'g'ri havola orqali kirish kerak
bo'lsa, ayting - Nginx konfiguratsiyasi uchun alohida yo'riqnoma beraman.

## Xavfsizlik

Ishga tushgach albatta:
- Admin parolini o'zgartiring (Profilim yoki Foydalanuvchilar bo'limidan)
- config/db.php dagi ma'lumotlarni productionga chiqarganda maxfiy saqlang
