# PrintCo — გამოწერა / Recurring Billing

ყოველთვიური ავტომატური გადახდის სისტემა. აწყობილია **provider-ნეიტრალურად**:
ბიზნეს-ლოგიკა არ არის მიბმული კონკრეტულ ბანკზე. ამჟამად ორი gateway არსებობს:

- **`mock`** — სრულად მუშა სიმულატორი, credentials-ის გარეშე (default).
- **`bog`** — Bank of Georgia (iPay) რეალური API.

provider-ის შესაცვლელად მხოლოდ ერთი კლასი იწერება — დანარჩენი არ იცვლება.

## როგორ მუშაობს

```
კლიენტი → subscribe.php → php/subscribe.php → Gateway.createInitialCheckout()
        → ბანკის ბარათის გვერდი (ტოკენიზაცია) → კლიენტი ბრუნდება return.php-ზე
ბანკი  → php/webhook.php (ხელმოწერის ვერიფიკაცია) → confirmPayment() → subscription = active
                                                     next_charge_date = დღეს + 1 თვე
cron   → cron/charge_due.php (დღეში ერთხელ) → due გამოწერებზე Gateway.chargeRecurring()
        ✓ წარმატება → next_charge_date += 1 თვე
        ✗ წარუმატ.  → dunning: retry მეორე დღეს, max ცდის შემდეგ → past_due
```

- ბარათის ნომერს **ჩვენ არასდროს ვინახავთ** — მხოლოდ ბანკის ტოკენს/parent order id-ს (PCI).
- თანხები ბაზაში მინორ ერთეულებში (თეთრი, int) — float-ის შეცდომების გარეშე.
- ყველა ოპერაცია **იდემპოტენტურია** — webhook/cron-ის გამეორება არ ჩამოაჭრის ორჯერ.

## ფაილები

| ფაილი | დანიშნულება |
|---|---|
| `php/config.php` | კონფიგი (env / `.env`) |
| `php/db.php` | SQLite + სქემა + გეგმების seed |
| `php/plans.php` | გეგმების განსაზღვრება |
| `php/SubscriptionService.php` | **billing engine** — მთელი ლოგიკა |
| `php/gateway/PaymentGateway.php` | provider-ის ინტერფეისი |
| `php/gateway/MockBogGateway.php` | მუშა სიმულატორი |
| `php/gateway/BogGateway.php` | BOG რეალური API |
| `php/subscribe.php` | გამოწერის დაწყება (POST) |
| `php/webhook.php` | ბანკის callback (ხელმოწერის ვერიფიკაცია) |
| `php/return.php` | კლიენტის დაბრუნების გვერდი |
| `php/mock_pay.php` | mock ბარათის გვერდი (mock რეჟიმში) |
| `php/cancel.php` | გამოწერის გაუქმება |
| `cron/charge_due.php` | ყოველდღიური scheduler |
| `admin/subscriptions.php` | ადმინ ხედი (HTTP Basic) |
| `subscribe.php` | გეგმების საჯარო გვერდი |
| `tests/test_recurring.php` | სრული ციკლის ტესტი |

## ლოკალურად გაშვება

```bash
cd printco
cp .env.example .env            # default-ად mock რეჟიმი
php -S 127.0.0.1:8080 -t .      # http://127.0.0.1:8080/subscribe.php
```

ტესტი:

```bash
php tests/test_recurring.php
```

scheduler ხელით (ან cron-ით დღეში ერთხელ):

```bash
php cron/charge_due.php             # დღევანდელი თარიღით
php cron/charge_due.php 2026-08-30  # კონკრეტული თარიღის სიმულაცია
```

mock რეჟიმის ტესტ-ჰუკები: ელ.ფოსტა, რომელიც შეიცავს `decline`-ს → ბარათი ყოველთვის
უარყოფს (dunning-ის გასატესტად).

## BOG-ზე გადართვა (production)

1. გააფორმე merchant კონტრაქტი Bank of Georgia-სთან (iPay / e-commerce).
2. მიიღე `client_id`, `client_secret` და callback-ის RSA public key.
3. `.env`-ში:
   ```
   PAYMENT_GATEWAY=bog
   APP_BASE_URL=https://შენი-დომენი
   BOG_CLIENT_ID=...
   BOG_CLIENT_SECRET=...
   BOG_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
   ```
4. callback URL ბანკის პანელში: `https://შენი-დომენი/php/webhook.php`.
5. `BogGateway`-ში endpoint-ების ზუსტი ბილიკები და „ბარათის შენახვის" ველი
   შეადარე შენი კონტრაქტის დოკუმენტაციას — კოდში მონიშნულია სად (`/ecommerce/orders`,
   automatic-charge path). ბიზნეს-ლოგიკა (`SubscriptionService`) არ იცვლება.

## Deploy (Fly.io)

`Dockerfile` უშვებს PHP-ს და ყოველდღიურ billing job-ს ერთ კონტეინერში.
უფრო სუფთა production-ვარიანტი: web ცალკე, ხოლო `cron/charge_due.php`
Fly-ის **scheduled machine**-ით დღეში ერთხელ.
