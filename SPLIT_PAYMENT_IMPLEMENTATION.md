# Split Payment Implementation Guide
## 15% Commission Model (10% Platform + 5% Developer)

This guide explains how to implement the split payment system with dual commission structure.

---

## 📊 Commission Structure

### Visa/Mastercard Payments:
- **Customer pays:** GHS 100.00
- **Platform commission:** GHS 10.00 (10%)
- **Developer commission:** GHS 5.00 (5%)
- **Restaurant receives:** GHS 85.00 (85%)
- **Total deduction:** 15%

### Mobile Money Payments:
- **Customer pays:** GHS 100.00
- **Platform commission:** GHS 10.00 (10%)
- **Developer commission:** GHS 1.00 (1%)
- **Restaurant receives:** GHS 89.00 (89%)
- **Total deduction:** 11%

---

## 🚀 Implementation Steps

### Step 1: Run Database Migration

Execute the split payment migration:

```bash
 
```

This will:
- ✅ Add bank account fields to restaurants table
- ✅ Add commission fields to orders table
- ✅ Create commission_records table for tracking
- ✅ Create ghana_banks table with all Ghana banks
- ✅ Create commission_config table
- ✅ Set up commission tracking views

### Step 2: Configure Paystack

1. **Get your Paystack API keys:**
   - Login to https://paystack.com
   - Go to Settings → API Keys & Webhooks
   - Copy your Secret Key and Public Key

2. **Update config/config.php:**
```php
define('PAYSTACK_PUBLIC_KEY', 'pk_live_xxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY', 'sk_live_xxxxxxxxxxxxx');
```

3. **Test with test keys first:**
```php
define('PAYSTACK_PUBLIC_KEY', 'pk_test_xxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY', 'sk_test_xxxxxxxxxxxxx');
```

### Step 3: Add Restaurants with Bank Accounts

**Option A - Use the New Add Restaurant Page:**

1. Navigate to: `admin/add_restaurant_split.php`
2. Fill in restaurant details
3. **Important:** Add bank account information:
   - Select bank from dropdown
   - Enter account number
   - Enter account holder name
4. Submit form

The system will automatically:
- Create restaurant record
- Create Paystack subaccount
- Generate QR code
- Link bank account for settlements

**Option B - Update Existing Restaurants:**

Run this script to add bank accounts to existing restaurants:

```php
// update_existing_restaurants.php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/payment_split.php';

$database = new Database();
$db = $database->getConnection();
$payment = new PaymentSplit($db);

// Get all restaurants without subaccounts
$query = "SELECT * FROM restaurants WHERE paystack_subaccount_code IS NULL";
$restaurants = $db->query($query)->fetchAll();

foreach ($restaurants as $restaurant) {
    echo "Processing: " . $restaurant['name'] . "\n";
    
    // You need to collect bank details for each restaurant
    // This is just an example - implement your own data collection
    
    $bank_code = readline("Enter bank code: ");
    $account_number = readline("Enter account number: ");
    
    // Create subaccount
    $result = $payment->createPaystackSubaccount(
        $restaurant['id'],
        $restaurant['name'],
        $bank_code,
        $account_number
    );
    
    if ($result['success']) {
        // Update restaurant
        $query = "UPDATE restaurants SET 
                  paystack_subaccount_code = :code,
                  bank_code = :bank_code,
                  account_number = :account_number
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'code' => $result['subaccount_code'],
            'bank_code' => $bank_code,
            'account_number' => $account_number,
            'id' => $restaurant['id']
        ]);
        echo "✓ Created subaccount\n";
    } else {
        echo "✗ Failed: " . $result['message'] . "\n";
    }
}
```

### Step 4: Update Order Processing

Replace the order processing endpoint:

**Before:** `process_order.php`
**After:** `process_order_split.php`

Update `menu.php` to use the new endpoint:

```javascript
// In menu.php, find the submitOrder function
function submitOrder(customerName, customerPhone, tableNumber, momoData) {
    // ...
    
    // Change this line:
    fetch('process_order_split.php', {  // Changed from process_order.php
        method: 'POST',
        // ...
    })
}
```

### Step 5: Test the System

1. **Create a test order:**
   - Go to restaurant menu (scan QR or visit directly)
   - Add items to cart
   - Proceed to checkout
   - Use Paystack test card: `4084084084084081`
   - CVV: `408`
   - Expiry: Any future date
   - OTP: `123456`

2. **Verify commission split:**
   - Check `commission_records` table
   - Verify amounts are calculated correctly
   - Check Paystack dashboard for split transaction

### Step 6: Access Developer Dashboard

1. Navigate to: `developer_dashboard.php`
2. Default password: `developer2026` (⚠️ CHANGE THIS!)
3. View your commission earnings

To change the password:
```php
// In developer_dashboard.php, line 6:
$developer_password = 'your_secure_password_here';
```

---

## 💳 How Payment Splitting Works

### Visa/Mastercard Flow (via Paystack):

```
1. Customer pays GHS 100
         ↓
2. Paystack processes payment
         ↓
3. Automatic split happens:
   - Platform account: GHS 10 (commission)
   - Developer receives: GHS 5 (from platform's portion)*
   - Restaurant receives: GHS 85 (to their bank account)
         ↓
4. Settlement:
   - Restaurant: T+1 (next business day)
   - Platform: Immediate
```

*Note: The 5% developer commission comes from the platform's 10% share. Paystack sees it as 15% total platform commission, then you manually distribute 5% to the developer.

### Mobile Money Flow:

```
1. Customer pays GHS 100 via Mobile Money
         ↓
2. MoMo provider processes
         ↓
3. Settlement to platform account
         ↓
4. Platform distributes:
   - Platform keeps: GHS 10
   - Developer gets: GHS 1
   - Restaurant gets: GHS 89 (manual transfer)
```

**Note:** Mobile Money doesn't support automatic splits like Paystack, so you'll need to manually transfer the restaurant portion.

---

## 📈 Commission Tracking

### View Commission Records:

```sql
-- All commission records
SELECT * FROM commission_records ORDER BY created_at DESC;

-- Developer commission summary
SELECT 
    SUM(developer_commission) as total_developer_commission,
    COUNT(*) as total_transactions
FROM commission_records
WHERE status = 'settled';

-- Platform commission summary
SELECT 
    SUM(platform_commission) as total_platform_commission
FROM commission_records
WHERE status = 'settled';

-- By payment method
SELECT 
    payment_method,
    SUM(developer_commission) as dev_commission,
    SUM(platform_commission) as platform_commission
FROM commission_records
WHERE status = 'settled'
GROUP BY payment_method;
```

### View in Dashboard:

**For Developer:**
- Access: `developer_dashboard.php`
- Shows: Your 5% (Visa) and 1% (MoMo) earnings

**For Platform Owner:**
- Access: `admin/reports.php`
- Shows: Platform's 10% commission

**For Restaurants:**
- Access: `restaurant/reports.php`
- Shows: Their 85% (Visa) or 89% (MoMo) earnings

---

## 🔧 Important Configuration

### Ghana Banks
All major Ghana banks are pre-loaded. To add more:

```sql
INSERT INTO ghana_banks (bank_name, bank_code) 
VALUES ('New Bank Name', 'BANK_CODE');
```

### Change Commission Rates

To change commission percentages:

1. **Update PHP constants** in `includes/payment_split.php`:
```php
const VISA_PLATFORM_COMMISSION = 10.00;  // Platform %
const VISA_DEVELOPER_COMMISSION = 5.00;  // Developer %
const VISA_TOTAL_COMMISSION = 15.00;     // Total %

const MOMO_PLATFORM_COMMISSION = 10.00;
const MOMO_DEVELOPER_COMMISSION = 1.00;
const MOMO_TOTAL_COMMISSION = 11.00;
```

2. **Update database config:**
```sql
UPDATE commission_config 
SET platform_percentage = 10.00,
    developer_percentage = 5.00,
    total_percentage = 15.00
WHERE payment_method = 'visa';
```

3. **Update Paystack subaccounts:**
   - You'll need to recreate subaccounts with new percentage
   - Or update existing subaccounts via Paystack API

---

## ⚠️ Important Notes

### Paystack Fees
Paystack charges approximately 1.95% + GHS 0.10 per transaction. Who pays this?

**Current setup:** Platform bears the cost (set in payment_split.php)

```php
'transaction_charge' => 0,  // Restaurant pays GHS 0 additional
'bearer' => 'account'       // Platform account pays Paystack fees
```

**To make restaurant pay:**
```php
'transaction_charge' => 195,  // 1.95% as basis points
'bearer' => 'subaccount'      // Restaurant pays Paystack fees
```

### Commission Example with Paystack Fees:

**If platform pays fees:**
- Customer pays: GHS 100.00
- Paystack fee: GHS 2.05 (paid by platform)
- Restaurant gets: GHS 85.00
- Platform gets: GHS 10.00 - GHS 2.05 = GHS 7.95
- Developer gets: GHS 5.00

**If restaurant pays fees:**
- Customer pays: GHS 100.00
- Paystack fee: GHS 2.05 (deducted from restaurant)
- Restaurant gets: GHS 85.00 - GHS 2.05 = GHS 82.95
- Platform gets: GHS 10.00
- Developer gets: GHS 5.00

### Settlement Timeline
- **Paystack:** T+1 (next business day)
- **Mobile Money:** Depends on provider (usually instant to 24 hours)

### Developer Payment Distribution
The 5% developer commission is part of the platform's 10% share. 

**How to pay the developer:**
1. Platform receives the full 10%
2. Platform owner manually pays developer their 5%
3. Platform keeps 5%

OR create a separate Paystack subaccount for the developer to automate this.

---

## 🛠️ Troubleshooting

### Subaccount Creation Fails
**Error:** "Account number not found"
- Verify the account number is correct
- Ensure bank code matches the bank
- Check Paystack dashboard for error details

### Payment Split Not Working
**Issue:** Restaurant not receiving funds
- Verify subaccount_code is saved in database
- Check Paystack dashboard → Subaccounts
- Ensure settlement bank is correct

### Commission Not Recording
**Issue:** commission_records table empty
- Check if payment status is 'completed'
- Verify payment_callback.php is being called
- Check database logs for errors

---

## 📞 Support

For Paystack issues:
- Email: support@paystack.com
- Docs: https://paystack.com/docs

For system issues:
- Check error logs in `/var/log/`
- Review database constraints
- Verify API credentials

---

## ✅ Go Live Checklist

Before launching:

- [ ] Change developer dashboard password
- [ ] Use live Paystack API keys (not test keys)
- [ ] Test with real small transaction (GHS 1)
- [ ] Verify commission splits are correct
- [ ] Confirm restaurant receives settlement
- [ ] Set up developer payment schedule
- [ ] Enable error monitoring
- [ ] Set up database backups
- [ ] Document settlement process
- [ ] Train platform admin on commission tracking

---

## 🎯 Summary

**What You Get:**
- ✅ Automatic 15% commission on Visa payments (10% platform + 5% developer)
- ✅ Automatic 11% commission on Mobile Money (10% platform + 1% developer)
- ✅ Instant settlement to restaurants (T+1 for Visa)
- ✅ Developer commission tracking dashboard
- ✅ Transparent commission records
- ✅ No manual fund distribution for Visa payments

**What You Need to Do:**
- Set up Paystack account
- Collect restaurant bank details
- Configure commission percentages
- Monitor settlements
- Pay developer their share manually (or automate)

**Result:**
A fully automated payment system where restaurants receive their money instantly, platform gets commission automatically, and developer commission is tracked for easy distribution!
