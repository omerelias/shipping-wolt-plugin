# דוח מפורט: תוסף OC WooCommerce Advanced Shipping (oc-woo-shipping)

מטרת הדוח: לספק את כל המידע הנדרש כדי שאינטגרציה חיצונית (למשל Wolt Drive API) תוכל לעבוד יחד עם התוסף — במיוחד חישובי משלוח, אזורי חלוקה, ושמירת נתוני הזמנה.

---

## 1. סקירה כללית

- **שם התוסף:** Original Concepts WooCommerce Advanced Shipping  
- **מזהה:** `oc_woo_advanced_shipping_method`  
- **קובץ ראשי:** `oc-woo-shipping.php`  
- **תלות:** WooCommerce (חובה), Composer (`vendor/autoload.php`), Carbon, וכו'.

התוסף מוסיף שיטת משלוח מותאמת ל‑WooCommerce: **אזורי חלוקה (קבוצות + מיקומים)**, **מחירי משלוח לפי אזור/עיר**, **חלונות זמן (slots)** למשלוח, ואיסוף כתובת מלאה (עיר, רחוב, מספר בית). יש גם **איסוף עצמי (Local Pickup)** — לא מפורט כאן כי המיקוד הוא משלוח.

---

## 2. איך נקבעת כתובת היעד (package destination)

WooCommerce קורא ל־`calculate_shipping( $package )` עם מערך `$package`. השדה הרלוונטי הוא `$package['destination']`.

### 2.1 מקור הנתונים: פילטר `woocommerce_cart_shipping_packages`

התוסף מעשיר את ה־packages דרך:

**קובץ:** `public/class-oc-woo-shipping-public.php` → `woocommerce_cart_shipping_packages_filter( $packages )`

- נתוני checkout/session נשמרים ב־`$_POST['post_data']` (או `$_POST` ב־checkout סופי) וב־session.
- התוסף מוסיף ל־`$packages[0]['destination']` את השדות הבאים.

### 2.2 מצב רגיל (ללא Google/Polygon)

- `destination['city']` — מזהה העיר (city code, מספרי או hash).
- שאר שדות WooCommerce הסטנדרטיים: `country`, `state`, `postcode`, `address`, `address_2` וכו'.

### 2.3 מצב Google ערים + פוליגונים (`ocws_common_use_google_cities_and_polygons` = 1)

בנוסף ל־`destination['city']` (שם עיר) מוזנים:

| שדה | תיאור |
|-----|--------|
| `address_coords` | `['lat' => ..., 'lng' => ...]` |
| `street` | שם רחוב |
| `house_num` | מספר בית |
| `city_name` | שם עיר (טקסט) |
| `city_code` | מזהה עיר (למשל Google Place ID) |
| `city` | מוגדר כ־`city_name` |

כלומר: ב־checkout יש גישה ל־**קואורדינטות**, **עיר**, **רחוב**, **מספר בית** — מתאים להעברה ל‑API חיצוני (למשל Wolt) לחישוב מחיר או יצירת משלוח.

---

## 3. זיהוי אזור חלוקה (location_code) מתוך ה־package

בתוך `OC_Woo_Advanced_Shipping_Method` (קובץ `includes/class-oc-woo-advanced-shipping-method.php`):

### 3.1 ללא פוליגונים

```php
$location_code = $package['destination']['city'];
```

### 3.2 עם פוליגונים / Google

1. אם קיים `destination['city_code']`:  
   `$location_code = OC_Woo_Shipping_Polygon::find_matching_gm_city( $package['destination']['city_code'] );`
2. אחרת, אם קיים `destination['address_coords']`:  
   `$location_code = OC_Woo_Shipping_Polygon::find_matching_polygon( lat, lng );`
3. אחרת, אם יש `street` + `house_num`:  
   קואורדינטות via `OC_Woo_Shipping_Polygon::get_address_coordinates( city, street, house_num )` ואז `find_matching_polygon( lat, lng )`.

ה־`location_code` הוא המפתח לכל הלוגיקה: קבוצה, מחיר, והאם המיקום בתוך אזור השירות.

---

## 4. מבנה נתונים: קבוצות (Groups) ומיקומים (Locations)

### 4.1 טבלאות DB (בקירוב)

- **`wp_oc_woo_shipping_groups`**  
  - `group_id`, `group_name`, `group_order`, `is_enabled`
- **`wp_oc_woo_shipping_locations`**  
  - `group_id`, `location_code`, `location_type` ('city' | 'polygon'), `location_name`, `location_order`, `is_enabled`, `gm_shapes`, `gm_streets`, `gm_place_id`
- **`wp_oc_woo_shipping_cities_base`** (רשימת ערים בסיס)  
  - `city_code`, `city_name`, `city_name_en`, `is_imported`

### 4.2 מיפוי location → group

**Data store:** `includes/data-stores/class-oc-woo-shipping-group-data-store.php`

```php
$group_id = $data_store->get_group_by_location( $location_code );
// SQL: SELECT group_id FROM wp_oc_woo_shipping_locations WHERE location_code = %s
```

כל **location_code** שייך ל־**group_id** אחד. קבוצה יכולה לכלול ערים ופוליגונים.

### 4.3 בדיקת "אזור בתוך שירות"

- `$data_store->is_location_enabled( $location_code )`  
- `$data_store->is_group_enabled( $group_id )`  
השיטת משלוח זמינה רק אם שני הערכים “enabled”.

---

## 5. חישוב מחיר משלוח — הלוגיקה המלאה

הכל מתרחש ב־`OC_Woo_Advanced_Shipping_Method::calculate_shipping( $package )` (`includes/class-oc-woo-advanced-shipping-method.php`).

### 5.1 סדר הבדיקות (תרשים זרימה)

1. **location_code**  
   אם אין — מוסיפים rate עם **מחיר ברירת מחדל** `get_option('ocws_default_shipping_price', 0)` ו־return.

2. **קופון משלוח חינם**  
   אם יש קופון עם `get_free_shipping()` — מוסיפים rate עם `cost => 0` ו־return.

3. **סה"כ עגלה**  
   `$total = WC()->cart->get_displayed_subtotal()` מינוס הנחות ומע"מ (בהתאם להגדרות תצוגה), מעוגל ל־`wc_get_price_decimals()`.

4. **קבוצה**  
   `$group_id = $data_store->get_group_by_location( $location_code )`. אם אין קבוצה — שוב rate עם `ocws_default_shipping_price` ו־return.

5. **משלוח חינם לפי סף קבוצה**  
   אופציה: `min_total_for_free_shipping` (ברמת קבוצה).  
   `OC_Woo_Shipping_Group_Option::get_option( $group_id, 'min_total_for_free_shipping', false )`.  
   אם יש ערך ו־`$total >= $min_total` → rate עם `cost => 0` ו־return.

6. **תמחור לפי כללים (price_depending) — ברמת קבוצה**  
   אופציה: `price_depending` — JSON עם `active` ו־`rules`. כל rule: `cart_value`, `shipping_price`.  
   `OC_Woo_Shipping_Group_Option::get_option( $group_id, 'price_depending', '' )`.  
   Rules ממוינים לפי `shipping_price` (עולה). לולאה: אם `$total >= $price_rule['cart_value']` → מוסיפים rate עם `cost => $price_rule['shipping_price']` ו־return.

7. **תמחור לפי כללים (price_depending) — ברמת מיקום (עיר)**  
   אותו מבנה, אבל:  
   `OC_Woo_Shipping_Group_Option::get_location_option( $location_code, $group_id, 'price_depending', '' )`.  
   עדיפות: כללי מיקום override כללי קבוצה (כי בוצעו אחרי).

8. **מחיר קבוע למיקום**  
   `OC_Woo_Shipping_Group_Option::get_location_option( $location_code, $group_id, 'shipping_price', 0 )`.  
   אם `option_value` לא ריק → `cost = round( option_value, wc_get_price_decimals() )` ו־return.

9. **אם אין קבוצה**  
   rate עם `ocws_default_shipping_price`.

### 5.2 סיכום מקורות המחיר

| מקור | איפה נשמר | פונקציה |
|------|-----------|---------|
| ברירת מחדל גלובלית | `ocws_default_shipping_price` | כשאין location או group |
| משלוח חינם | קופון WooCommerce free shipping | - |
| סף חינם לקבוצה | `ocws_group{id}_min_total_for_free_shipping` | get_option ברמת group |
| כללים לפי סל (קבוצה) | `ocws_group{id}_price_depending` (JSON) | rules: cart_value, shipping_price |
| כללים לפי סל (מיקום) | `ocws_location{code}_price_depending` (JSON) | כמו למעלה, per location |
| מחיר קבוע למיקום | `ocws_location{code}_shipping_price` או ברירת מחדל מקבוצה | get_location_option |

### 5.3 מבנה JSON של price_depending

```json
{
  "active": true,
  "rules": [
    { "cart_value": 0, "shipping_price": 30 },
    { "cart_value": 150, "shipping_price": 15 },
    { "cart_value": 300, "shipping_price": 0 }
  ]
}
```

Rules ממוינים לפי `shipping_price` עולה. נלקח הראשון ש־`total >= cart_value`.

---

## 6. אופציות קבוצה ומיקום (Group Options)

**קובץ:** `includes/class-oc-woo-shipping-group-option.php`

- **קבוצה:** `get_option( 'ocws_group' . $group_id . '_' . $option_name, default )`  
  עם אפשרות "use default": `ocws_group{id}_{option}_ud` = '1' → משתמשים ב־`ocws_default_{option}`.
- **מיקום:** `get_location_option( $location_code, $group_id, $option_name, default )`  
  אם "use default" → מחזירים את ערך הקבוצה; אחרת `ocws_location{code}_{option}`.

שמות רלוונטיים למשלוח ולחישוב:

- `shipping_price` — מחיר משלוח קבוע
- `min_total` — מינימום סל להצגת השיטה (לא משפיע על המחיר, רק על זמינות/הודעות)
- `min_total_for_free_shipping` — סף למשלוח חינם
- `price_depending` — JSON של כללי תמחור לפי סל
- `delivery_scheduling_type`, `delivery_schedule_weekly` / `delivery_schedule_dates`, `closing_weekdays`, `closing_dates`, `preorder_days`, `min_wait_times`, וכו' — רלוונטיים ל־slots (ראו להלן).

---

## 7. חלונות זמן משלוח (Slots)

### 7.1 מטרה

הלקוח בוחר **תאריך** ו**חלון זמן** (למשל 10:00–12:00). הנתונים נשמרים בהזמנה ומשמשים לתצוגה וייצוא.

### 7.2 מחלקות עיקריות

- **OC_Woo_Shipping_Slots** (`includes/class-oc-woo-shipping-slots.php`) — בניית רשימת תאריכים + slots זמינים ל־checkout לפי `location_code`.
- **OC_Woo_Shipping_Schedule** (`includes/class-oc-woo-shipping-schedule.php`) — הגדרת לוח: ימים (weekly: 0–6, או dates ספציפיים), לכל יום: `periods` (start/end), `max_hour`, `filter_picker` (same_day / X days before).

הגדרות נטענות מקבוצה: `delivery_scheduling_type`, `delivery_schedule_weekly`/`delivery_schedule_dates`, `preorder_days`, `min_wait_times`, `closing_weekdays`, `closing_dates`, `max_hour_for_today`, `delivery_schedule_repeat`, וכו'.

### 7.3 פורמט slot

לכל slot: `['start' => '10:00', 'end' => '12:00', 'data' => [...]]`. תאריך בפורמט `d/m/Y`.

---

## 8. שמירת נתוני משלוח בהזמנה (Order Meta)

רלוונטי ל־Wolt: אילו שדות יש בהזמנה אחרי checkout.

**קובץ:** `includes/class-oc-woo-shipping-info.php` → `OC_Woo_Shipping_Info::save_to_order( $order )`  
נקרא מ־`public/class-oc-woo-shipping-public.php` ב־`woocommerce_checkout_order_processed` (ו־save_shipping_to_order).

### 8.1 Meta של ההזמנה (post meta)

| Meta key | תיאור |
|----------|--------|
| `ocws_shipping_tag` | `'shipping'` (להבדיל מ־pickup) |
| `ocws_shipping_info_date` | תאריך משלוח (d/m/Y) |
| `ocws_shipping_info_date_sortable` | תאריך Y/m/d למיון |
| `ocws_shipping_info_slot_start` | תחילת חלון (שעה) |
| `ocws_shipping_info_slot_end` | סוף חלון (שעה) |
| `ocws_shipping_group` | group_id של אזור המשלוח |
| `_billing_city` | אחרי שמירה: שם עיר (טקסט) אם היה קוד עיר |
| `_billing_city_name` | שם עיר לתצוגה |
| `_billing_city_code` | קוד עיר (אם יש) |
| `_shipping_city`, `_shipping_city_name`, `_shipping_city_code` | מקבילים למשלוח |
| `_billing_address_1` | נבנה מ־street + house (ראו ocws_save_full_address_to_order) |
| `_billing_street`, `_billing_house_num`, `_billing_apartment`, `_billing_floor`, `_billing_enter_code` | שדות כתובת מפורטים |
| `_billing_address_coords` | קואורדינטות (אם מופעל polygon) |

בנוסף, ב־**shipping item** (WC_Order_Item_Shipping) נשמר meta:

- `ocws_shipping_info` — מחרוזת serialize של `['date','slot_start','slot_end']`.

אין חישוב מחיר ב־save — המחיר כבר חושב ב־`calculate_shipping` ונשמר על ידי WooCommerce כ־shipping line.

---

## 9. Hooks מרכזיים לאינטגרציה

| Hook | קובץ/מקום | שימוש אפשרי |
|------|------------|-------------|
| `woocommerce_cart_shipping_packages` | Public | העשרת `destination` (כתובת, קואורדינטות, עיר) — כבר בשימוש |
| `woocommerce_shipping_methods` | core-functions | רישום `oc_woo_advanced_shipping_method` |
| `woocommerce_shipping_init` | core-functions | טעינת class שיטת המשלוח |
| `woocommerce_after_checkout_validation` | class-oc-woo-shipping.php, OC_Woo_Shipping_Info, OC_Woo_Advanced_Shipping_Method | ולידציה checkout ו־is_applicable |

### 9.1 הוספת/שינוי מחיר משלוח מבחוץ

- WooCommerce מחשב shipping דרך `WC_Shipping::calculate_shipping()` שקורא ל־`calculate_shipping()` על כל שיטה.  
- **אפשרות 1:** תוסף נפרד שמוסיף שיטת משלוח נוספת (למשל "משלוח וולט") ומחשב עלות מ־API — הלקוח בוחר בין "משלוח רגיל" ל־"וולט".  
- **אפשרות 2:** פילטר על ה־rates אחרי חישוב — למשל `woocommerce_package_rates` — ולשנות או להחליף את ה־cost של `oc_woo_advanced_shipping_method` לפי קריאה ל־Wolt API (כתובת/קואורדינטות מ־`$package['destination']`).  
- **אפשרות 3:** להשאיר תמחור לפי אזורים (כמו היום) ולתאם את המחירים באדמין (למשל 28 ₪ וולט → 30 ₪ באתר) — ללא API בזמן אמת.

---

## 10. זרימת חישוב — סיכום קצר

1. משתמש בוחר עיר/כתובת (ובמקרה polygon: רחוב, מספר בית, אולי קואורדינטות).  
2. `woocommerce_cart_shipping_packages` ממלא את `destination` (כולל city, street, house_num, address_coords, city_code אם רלוונטי).  
3. WooCommerce קורא ל־`OC_Woo_Advanced_Shipping_Method::calculate_shipping( $package )`.  
4. מתוך `destination` מפיקים `location_code` (עיר או פוליגון).  
5. מזהים `group_id` דרך `get_group_by_location( $location_code )`.  
6. מחיר: קופון חינם → סף חינם קבוצה → price_depending (group) → price_depending (location) → shipping_price ל־location.  
7. `add_rate( id, label, cost )` — WooCommerce מציג את האפשרות ב־checkout.  
8. ב־checkout נבחרים תאריך ו־slot; ב־`save_to_order` נשמרים date, slot_start, slot_end ו־meta כתובת/עיר.

---

## 11. רשימת קבצים קריטיים לחישוב ולתמחור

| קובץ | תפקיד |
|------|--------|
| `oc-woo-shipping.php` | bootstrap, קבועים, הרצת plugin |
| `includes/class-oc-woo-shipping.php` | טעינת dependencies, הגדרת hooks, locations |
| `includes/class-oc-woo-advanced-shipping-method.php` | **חישוב מחיר**, is_available, is_applicable |
| `includes/oc-woo-shipping-core-functions.php` | ocws_add_shipping_method, ocws_shipping_method_init, פונקציות עזר |
| `includes/class-oc-woo-shipping-group-option.php` | get_option, get_location_option (מחירים וכללים) |
| `includes/class-oc-woo-shipping-group.php` | מודל קבוצה, מיקומים, get_location_name_by_code |
| `includes/class-oc-woo-shipping-locations.php` | ערים (cities), get_city_name |
| `includes/data-stores/class-oc-woo-shipping-group-data-store.php` | get_group_by_location, is_location_enabled, is_group_enabled |
| `public/class-oc-woo-shipping-public.php` | woocommerce_cart_shipping_packages_filter, checkout fields, save_shipping_to_order |
| `includes/class-oc-woo-shipping-info.php` | שמירת תאריך/slot להזמנה, validate_checkout_posted_data |
| `includes/class-oc-woo-shipping-slots.php` | חישוב slots זמינים לפי location |
| `includes/class-oc-woo-shipping-schedule.php` | לוח זמנים (weekly/dates, periods) |
| `includes/class-oc-woo-shipping-polygon.php` | find_matching_polygon, find_matching_gm_city, get_address_coordinates (אם בשימוש) |

---

## 12. המלצות לאינטגרציית Wolt

- **כתובת מלאה:** ב־package כבר זמינים `street`, `house_num`, `city`, `address_coords` (אם מופעל polygon). בהזמנה: `_billing_address_1`, `_billing_street`, `_billing_house_num`, `_billing_city_name`, `_shipping_*` — מתאים לשליחה ל־Wolt.  
- **מחיר בזמן אמת (אפשרות 1):** להשתמש ב־`woocommerce_package_rates` ולעדכן את ה־cost של `oc_woo_advanced_shipping_method` לפי תשובת Wolt API (עם fallback למחיר הקיים אם ה־API נכשל).  
- **מחיר סטטי לפי אזור (אפשרות 2):** להשאיר את מנגנון ה־groups/locations ולעדכן `shipping_price` ו/או `price_depending` כך שישקפו עלות Wolt (כולל מרווח). אם עלות Wolt משתנה — צריך עדכון ידני או cron שיעדכן אופציות.  
- **תאריך וחלון:** `ocws_shipping_info_date`, `ocws_shipping_info_slot_start`, `ocws_shipping_info_slot_end` — ניתן להעביר ל־Wolt כ־preferred delivery window אם ה־API תומך.

הדוח הזה מכסה את חישובי המשלוח, מקורות המחיר, מבנה האזורים וההזמנה — מספיק כדי לתכנן או לממש אינטגרציה (למשל עם Wolt) בלי לשבור את הלוגיקה הקיימת של התוסף.
