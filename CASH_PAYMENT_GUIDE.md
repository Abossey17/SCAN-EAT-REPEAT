# Cash Payment & Waiting Number Implementation Guide

## 🎯 Overview

This guide covers the implementation of:
1. **Cash Payment Option** - No commission on cash orders
2. **Waiting Numbers** - Unique identifiers for easy order tracking
3. **Receipt Generation** - Customer and kitchen receipts
4. **Display Board** - Real-time order status display

---

## 📊 New Features Summary

### 1. Cash Payment
- **No commission** on cash payments (0% platform, 0% developer)
- Orders auto-confirmed when placed with cash
- Payment marked as "pending" until restaurant confirms receipt
- Customer receives receipt immediately
- Restaurant collects cash on delivery

### 2. Waiting Numbers
- **Unique daily numbers** for each restaurant (001, 002, 003...)
- **Multiple patterns** supported: Numeric, Alpha, Alpha-Numeric, Emoji
- **Resets daily** - starts from 001 each day
- Displayed prominently on receipts
- Used for order identification by staff

### 3. Receipt System
- **Customer Receipt** - Full receipt with waiting number
- **Kitchen Receipt** - Order details for kitchen staff
- **Auto-print** capability for kitchen
- Tracks all printed receipts in database

### 4. Display Board
- **Real-time** waiting number display
- Auto-refreshes every 10 seconds
- Shows preparing, ready, and completed orders
- Full-screen mode for restaurant TVs

---

## 🚀 Installation Steps

### Step 1: Run Database Migration

```bash
mysql -u root -p qr_restaurant_system < database_cash_payment_migration.sql
```

This creates:
- ✅ Cash payment support in `payment_method` enum
- ✅ `waiting_number` column in orders table
- ✅ `waiting_number_sequences` table for tracking
- ✅ `receipts` table for tracking prints
- ✅ Cash commission config (0%)

### Step 2: Update Order Processing

Replace your order processing endpoint with the new one:

**Option A - Fresh Installation:**
Use `process_order_with_cash.php` as your main endpoint

**Option B - Existing System:**
Update `menu.php` to point to the new endpoint:

```javascript
// In menu.php, find submitOrder function
fetch('process_order_with_cash.php', {  // Updated endpoint
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(orderData)
})
```

### Step 3: Update Menu UI for Cash Option

Add cash payment option to your menu checkout:

```html
<!-- In menu.php payment options -->
<div class="payment-options">
    <label class="payment-option">
        <input type="radio" name="payment_method" value="cash" checked>
        <span>💵 Cash on Delivery</span>
    </label>
    <label class="payment-option">
        <input type="radio" name="payment_method" value="visa">
        <span>💳 Visa/Mastercard (15% fee)</span>
    </label>
    <label class="payment-option">
        <input type="radio" name="payment_method" value="mobile_money">
        <span>📱 Mobile Money (11% fee)</span>
    </label>
</div>
```

### Step 4: Update Success Flow

After order placement, redirect based on payment method:

```javascript
if (data.success) {
    if (data.payment_method === 'cash') {
        // Redirect to receipt page
        window.location.href = data.redirect_url;
    } else if (data.payment_method === 'visa') {
        // Redirect to Paystack
        window.location.href = data.payment_url;
    } else {
        // Mobile money - show waiting page
        showMoMoWaiting(data);
    }
}
```

---

## 💰 Commission Structure with Cash

| Payment Method | Platform | Developer | Restaurant | Total Fee |
|----------------|----------|-----------|------------|-----------|
| **Cash** | 0% | 0% | 100% | **0%** |
| **Visa/Card** | 10% | 5% | 85% | **15%** |
| **Mobile Money** | 10% | 1% | 89% | **11%** |

**Why no commission on cash?**
- Cash is paid directly to restaurant
- No online processing fees
- Restaurant collects full amount
- Platform makes money from Visa/MoMo transactions

---

## 🎯 Waiting Number Patterns

Choose a pattern for your waiting numbers:

### Pattern 1: Numeric (Default)
```
001, 002, 003... 999
```
Best for: Most restaurants, simple and clear

### Pattern 2: Alpha
```
A, B, C... Z, AA, AB, AC...
```
Best for: Smaller operations, fewer daily orders

### Pattern 3: Alpha-Numeric
```
A1, A2... Z99
```
Best for: Medium-sized restaurants

### Pattern 4: Emoji
```
🎯1, 🎯2, 🎯3...
```
Best for: Trendy/casual restaurants, visual appeal

**To change pattern:**
Edit `process_order_with_cash.php`:
```php
// Line with generateWaitingNumber
$waiting_number = $waitingNumber->generateWaitingNumber($restaurant_id, 'NUMERIC');
// Change to: 'ALPHA', 'ALPHA_NUM', or 'EMOJI'
```

---

## 📱 Receipt Usage

### Customer Receipt (`receipt.php`)

**Features:**
- Large waiting number display
- Complete order details
- Payment method and status
- QR code for future reference
- Print button
- Cash payment reminder

**Access:**
Automatically shown after cash order placement:
```
https://yoursite.com/receipt.php?order_id=123
```

**Use Cases:**
- Customer keeps as reference
- Shows to waitress when mentioning number
- Proof of order
- Print for personal records

### Kitchen Receipt (`restaurant/kitchen_receipt.php`)

**Features:**
- Bold waiting number
- Order type and table
- Items to prepare
- Customer name/phone
- Auto-print on load
- Cash payment notice

**Access:**
From restaurant orders page:
```
https://yoursite.com/restaurant/kitchen_receipt.php?order_id=123
```

**Workflow:**
1. New order arrives
2. Restaurant clicks "Print Kitchen Receipt"
3. Opens in new tab
4. Auto-prints immediately
5. Kitchen staff prepares order
6. Calls waiting number when ready

---

## 📺 Display Board Setup

The waiting number display board shows current orders on a screen.

### Setup for Restaurant TV/Monitor:

1. **Access the board:**
   ```
   https://yoursite.com/restaurant/waiting_board.php
   ```

2. **Login with restaurant credentials**

3. **Press F11** for full-screen mode

4. **Leave it running** - auto-refreshes every 10 seconds

### Display Features:
- ✅ Large waiting numbers
- ✅ Order status (Preparing, Ready)
- ✅ Table numbers for dine-in
- ✅ Time since order placed
- ✅ Color-coded by status
- ✅ Glowing animation for ready orders

### Recommended Setup:
- **Small restaurant:** Tablet (10-12 inches)
- **Medium restaurant:** Monitor (24-32 inches)
- **Large restaurant:** TV (40+ inches)
- **Multiple stations:** One screen per station

---

## 🔄 Order Workflow with Cash Payment

### Customer Journey:

1. **Scan QR code** → Access menu
2. **Select items** → Add to cart
3. **Checkout** → Choose "Cash on Delivery"
4. **Enter details** → Name, phone, table
5. **Place order** → Receive waiting number
6. **Get receipt** → Shows number prominently
7. **Wait** → Watch display board
8. **Hear number** → Collect order
9. **Pay cash** → Hand money to staff
10. **Enjoy meal** ✅

### Restaurant Journey:

1. **Receive order** → Notification/sound
2. **Print kitchen receipt** → Shows waiting number
3. **Prepare food** → Follow order items
4. **Mark as ready** → Updates display board
5. **Call waiting number** → "Number 042 ready!"
6. **Deliver order** → Customer shows receipt
7. **Collect cash** → Count and confirm
8. **Mark as completed** → Close order

---

## 🎨 Customization Options

### Waiting Number Voice Announcements

Add text-to-speech for calling numbers:

```javascript
// Add to waiting_board.php
function announceNumber(number) {
    const speech = new SpeechSynthesisUtterance();
    speech.text = "Number " + number + " your order is ready";
    speech.lang = 'en-US';
    speech.rate = 0.9;
    window.speechSynthesis.speak(speech);
}

// Auto-announce when order becomes ready
// Add this to your order update function
if (newStatus === 'ready') {
    announceNumber(waitingNumber);
}
```

### SMS Notifications

Send SMS when order is ready:

```php
// In restaurant/orders.php when updating to 'ready'
if ($new_status === 'ready') {
    // Send SMS using your SMS provider
    $message = "Hi {$customer_name}, your order #{$waiting_number} is ready for pickup!";
    sendSMS($customer_phone, $message);
}
```

### Custom Waiting Number Prefix

Add restaurant prefix to waiting numbers:

```php
// In includes/waiting_number.php, formatWaitingNumber()
public function formatWaitingNumber($number, $pattern, $restaurant_code = '') {
    $formatted = // ... existing formatting code
    
    if ($restaurant_code) {
        return $restaurant_code . '-' . $formatted;
    }
    
    return $formatted;
}

// Usage: ABC-001, ABC-002 (where ABC is restaurant code)
```

---

## 📊 Tracking & Reports

### Daily Waiting Number Report

```sql
-- Orders by waiting number today
SELECT 
    waiting_number,
    order_number,
    customer_name,
    order_status,
    payment_method,
    total_amount,
    created_at
FROM orders
WHERE restaurant_id = YOUR_RESTAURANT_ID
AND DATE(created_at) = CURDATE()
ORDER BY waiting_number;
```

### Cash Payment Summary

```sql
-- Cash orders today
SELECT 
    COUNT(*) as total_cash_orders,
    SUM(total_amount) as total_cash_collected
FROM orders
WHERE restaurant_id = YOUR_RESTAURANT_ID
AND payment_method = 'cash'
AND DATE(created_at) = CURDATE();
```

### Receipt Tracking

```sql
-- How many receipts printed
SELECT 
    receipt_type,
    COUNT(*) as total_printed
FROM receipts
WHERE restaurant_id = YOUR_RESTAURANT_ID
AND DATE(printed_at) = CURDATE()
GROUP BY receipt_type;
```

---

## ⚡ Performance Tips

### 1. Auto-Reset Waiting Numbers

Add a daily cron job to reset sequences:

```bash
# Add to crontab (runs at midnight daily)
0 0 * * * php /path/to/reset_waiting_numbers.php
```

Create `reset_waiting_numbers.php`:
```php
<?php
require_once 'config/database.php';
require_once 'includes/waiting_number.php';

$database = new Database();
$db = $database->getConnection();
$waitingNumber = new WaitingNumber($db);

$deleted = $waitingNumber->resetDailySequences();
echo "Reset $deleted old sequences\n";
```

### 2. Cache Display Board Data

For high-traffic restaurants, cache the display board:

```php
// In waiting_board.php
$cache_key = 'waiting_board_' . $restaurant_id;
$cache_time = 5; // seconds

if ($cached = getFromCache($cache_key)) {
    $current_orders = $cached;
} else {
    $current_orders = $waitingNumber->getCurrentWaitingNumbers($restaurant_id, 20);
    saveToCache($cache_key, $current_orders, $cache_time);
}
```

### 3. Optimize Receipt Printing

Use thermal printers for fast kitchen receipts:
- 80mm thermal printer
- USB or Network connection
- Auto-print on order receive
- Faster than regular printers

---

## 🐛 Troubleshooting

### Waiting Numbers Not Generating

**Issue:** Orders show NULL for waiting_number

**Fix:**
```sql
-- Check if column exists
DESCRIBE orders;

-- If missing, add it
ALTER TABLE orders ADD COLUMN waiting_number VARCHAR(10) AFTER order_number;
```

### Duplicate Waiting Numbers

**Issue:** Two orders have same number

**Fix:**
```sql
-- Check for duplicates
SELECT waiting_number, COUNT(*) 
FROM orders 
WHERE DATE(created_at) = CURDATE()
GROUP BY waiting_number 
HAVING COUNT(*) > 1;

-- Ensure unique index exists
ALTER TABLE orders 
ADD UNIQUE INDEX idx_waiting_number (restaurant_id, waiting_number, DATE(created_at));
```

### Display Board Not Refreshing

**Issue:** Board shows old data

**Fix:**
1. Check auto-refresh JavaScript is working
2. Clear browser cache (Ctrl+Shift+R)
3. Check if restaurant is logged in
4. Verify database connection

### Kitchen Receipt Auto-Print Fails

**Issue:** Receipt doesn't print automatically

**Fix:**
1. Check browser print settings
2. Enable pop-ups for the site
3. Set default printer in browser
4. Test with `window.print()` in console

---

## ✅ Testing Checklist

Before going live:

**Cash Payment:**
- [ ] Place cash order successfully
- [ ] Verify 0% commission recorded
- [ ] Check order auto-confirmed
- [ ] Receipt shows cash payment notice
- [ ] Restaurant can mark payment received

**Waiting Numbers:**
- [ ] Numbers generate sequentially
- [ ] Numbers reset daily (test with date change)
- [ ] Unique per restaurant
- [ ] Display correctly on receipts
- [ ] Show on display board

**Receipts:**
- [ ] Customer receipt accessible
- [ ] Kitchen receipt prints
- [ ] Receipt tracking recorded in database
- [ ] Print button works
- [ ] Mobile responsive

**Display Board:**
- [ ] Shows current orders
- [ ] Auto-refreshes
- [ ] Status colors correct
- [ ] Ready orders highlighted
- [ ] Full-screen mode works

---

## 🎯 Summary

**What You Now Have:**

✅ **Cash Payment Option** - 0% commission, instant orders
✅ **Waiting Numbers** - Easy customer identification  
✅ **Customer Receipts** - Professional order confirmation
✅ **Kitchen Receipts** - Clear order preparation
✅ **Display Board** - Real-time order status
✅ **Full Tracking** - All orders and receipts logged

**Benefits:**

👍 Customers love the waiting number system
👍 Staff find orders quickly without confusion
👍 Cash option increases order volume
👍 Professional receipt system builds trust
👍 Display board reduces "where's my order?" questions
👍 No commission on cash = more restaurant revenue

**Next Steps:**

1. Run database migration
2. Test with sample orders
3. Train restaurant staff
4. Set up display screen
5. Print test receipts
6. Go live! 🚀

---

## 📞 Support

For issues or questions:
- Check troubleshooting section
- Review database logs
- Test with sample data
- Verify all files uploaded

Your system now has complete cash payment support with a professional waiting number system!
