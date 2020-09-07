﻿Instalace modulu:
- Zapněte konzoli na vašem serveru (např. putty) a přesuňte se na kořenový adresář s e-shopem (je to ten adresář kde po instalaci vidíte adresáře source, var a vendor
- Spusťte instalaci pomoci composeru: composer require gpwebpay/gpwebpay-oxid6
- Do adresáře source/modules/gpwebpay/gpwebpay-oxid6/cert/ nakopírujte váš privátní klíč (*.key) a veřejný klíč (*.pem)
- Přihlaste se do administrace OXID shopu a v levém menu zvolte Extensions / Modules 
- Klikněte na GP webpay a následně vpravo klikněte na tlačítko Activate. Timto se aktivuje modul.
- Dole přepněte na záložku Settings a poté na volbu GP config

Konfigurace modulu:
Na konfigurační stránce se nachází položky které je potřeba doplnit, zde je stručný popis

GP gateway URL
- adresa na které komunikuje brána. 
https://test.3dsecure.gpwebpay.com/pgw/order.do pro testovací prostředí
https://3dsecure.gpwebpay.com/pgw/order.do pro produkční trostředí

Merchant number
- číslo přiřazené k obchodu od GP webpay

Public key filename
- jméno souboru (*.pem) v adresáři source/modules/gpwebpay/gpwebpay-oxid6/cert/ např. verejnyklic.pem

Private key filename
- jméno souboru (*.key) v adresáři source/modules/gpwebpay/gpwebpay-oxid6/cert/ např. soukromyklic.key

Private key password
- vaše heslo k privátnímu klíči

Transfer type
- volba zda se má obnos stáhnout z účtu zákazníka hned, nebo se pouze autorizuje a ke stáhnutí dojde později

First order number (from)
- číslo od kterého se začínají počítat objednávky na straně GPE. Nelze měnit směrem dolů jelikož by mohlo dojít k duplicitním číslům objednávek. Od tohoto čísla se budou číslovat unikátní operace s platební branou



OXID e-shop vyžaduje správně nakonfigurované nastavení, aby vše korektně fungovalo. 
Je potřeba tedy:
- napárovat platbu k zemi doručení (Shop Settings / Payment Methods / GP webpay / Country / Assign Countries)
- napárovat doručování k zemi (Shop Settings / Shipping Methods / "volba doručení" / Main / Assign Countries)
- napárovat platbu k doručování (Shop Settings / Shipping Methods / "volba doručení" / Payment / Assign Payment Methods)
- napárovat poštovné k zemi (Shop Settings / Shipping Cost Rules / "volba doručení" / Main / Assign Countries)
- vložit do systému měnu (Master Settings / Core Settings / Settings / Other settings / Add or remove currencies )
... a další obdobné párování pokud vkládáte novou měnu, nový stát, nové doručení   
