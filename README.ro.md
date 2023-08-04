#  Maib Payment Gateway Plugin for WooCommerce
Acest plugin vă permite să integrați magazinul dvs. online cu noul API e-commerce de la **maib** pentru a accepta plăți online (Visa / Mastercard / Google Pay / Apple Pay).

## Descriere
Pentru a avea posibilitatea de a folosi acest plugin trebuie să fiți înregistrat pe platforma [maibmerchants.md](https://maibmerchants.md).

Imediat după înregistrare, veți putea efectua plăți în mediul de test cu datele de acces din Proiectul de test.

Pentru a efectua plăți reale trebuie să efectuați cel puțin o tranzacție reușită în mediul de test și să parcurgeți pașii necesari pentru activarea Proiectului de producție.

### Pași pentru activarea Proiectului de Producție
1. Profil completat pe platforma maibmerchants
2. Profil validat
3. Contract e-commerce

## Funcțional
**Plăți online**: Visa / Mastercard / Apple Pay / Google Pay.

**Două tipuri de plăți** (în dependență de setările Proiectului dvs.):

  *1. Plăți directe* - dacă tranzacția este cu succes suma este extrasă imediat din contul cumpărătorului..

  *2. Plăți în 2 etape* - dacă tranzacția este cu succes suma este doar blocată pe contul cumpărătorului (tranzacție autorizată), pentru a extrage suma  va trebui ulterior să finalizați tranzacția din comanda creată folosind acțiunea - _Finisare plată în 2 etape_. 

**Trei valute**: MDL / USD / EUR (în dependență de setările Proiectului dvs).

**Returnare plată**: prin funcționalul standart WooComerce de returnare din comanda creată. Suma returnată poate fi mai mică sau egală cu suma tranzacției. Acțiunea de returnare este posibilă doar o singură dată pentru fiecare tranzacție reușită.

## Cerințe
- Înregistrare pe platforma maibmerchants.md
- WordPress ≥ v. 4.8
- WooCommerce ≥ v. 3.3
- PHP ≥ v. 5.6 (cu extensiile curl și json activate)

## Instalare
### Din panoul de admnistrare WP
1. Accesați din meniu **Module > Adaugă modul**
2. Căutați în bara de căutare: Maib Payment Gateway for WooCommerce
3. Faceți clic pe _Instalează acum_ și așteptați până când plugin-ul va fi instalat cu succes
4. Faceți clic pe _Activează_ pentru a activa pluginul

### Prin FTP
1. Descărcați plugin-ul din repozitoriu WordPress.
2. Dezarhivați.
3. Încărcați folderul în directorul _/wp-content/plugins/_.
4. Accesați în meniu _Module_ și activați plugin-ul.

## Activare metodă de plată
1. Accesați **WooCommerce > Setări > Plăți**
2. Activați metoda de plată Maib Payment Gateway
3. Faceți clic pe _Administrează_ pentru a efectua setările necesare.

## Setări
1. Activare/Dezactivare
2. Denumire - Denumirea Metodei de plată afișată Cumpărătorului pe pagina de Checkout.
3. Descriere - Descrierea Metodei de plată afișată Cumpărătorului pe pagina de Checkout.
4. Mod depanare - Activarea înregistrării mesajelor cu erori în sistemul de Jurnale WooCommerce. Pentru a vedea mesajele de depanare accesați _Vezi jurnalele_ și alegeți fișierul _maib_gateway__ cu data pentru care doriți să vedeți mesajele.
5. Tipul plăților - Plăți directe sau Plăți în 2 etape.
6. Descrierea comenzii - Informația despre comandă afișată Cumpărătorului pe pagina băncii de introducere a datelor cardului.
7. Project ID - Project ID al Proiectului în maibmerchants.
8. Project Secret - Este disponibil după activarea Proiectului în maibmerchants.md
9. Signature Key - Pentru validarea notificărilor cu starea tranzacțiilor pe Callback URL. Este disponibil după activarea Proiectului în maibmerchants.
10. Starea comenzii: Plată cu succes - Starea comenzii dacă tranzacția s-a finalizat cu succes. Implicit: În procesare.
11. Starea comenzii: Plată în 2 etape autorizată - Starea comenzii dacă tranzacția este autorizată cu succes. Implicit: În așteptare.
12. Starea comenzii: Plată eșuată - Starea comenzii dacă tranzacția a eșuat. Implicit: Eșuată.
13. Setări Proiect - Adăugați link-urile prestabilite pentru Ok URL / Fail URL / Callback URL în cîmpurile respective a Proiectului în maibmerchants.

## Depanare
Activați depanarea în setările plugin-ului și accesați fișierul cu log-uri.

Dacă aveți nevoie de asistență suplimentară, vă rugăm să nu ezitați să contactați echipa de asistență ecommerce **maib**, expediind un e-mail la ecom@maib.md.

În e-mailul dvs., asigurați-vă că includeți următoarele informații:
- Numele comerciantului
- Project ID
- Data și ora tranzacției cu erori
- Erori din fișierul cu log-uri
