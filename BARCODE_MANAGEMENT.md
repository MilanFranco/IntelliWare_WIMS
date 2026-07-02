# Barcode Management Guide — IntelliWare Inventory System

## Overview

IntelliWare uses **manufacturer-assigned barcodes** as the primary product identifier in retail inventory operations. Barcodes are printed on product packaging (bottles, cans, boxes, sachets, medicine packages, grocery items, household products, etc.) and are scanned into the system for inventory tracking.

### Important Principles

✅ **System supports:**
- Reading/scanning manufacturer barcodes from product packaging
- Storing barcodes as unique product identifiers
- Using barcodes as primary lookup keys for inventory transactions
- Multiple barcode formats: EAN-13, EAN-8, UPC-A, UPC-E, Code 128, Code 39, Code 93, ITF

❌ **System does NOT:**
- Generate barcodes
- Assign barcodes automatically
- Create barcode images
- Print barcodes
- Create products with auto-assigned barcode numbers

---

## Workflow: Product Registration

### Step 1: Scan the Barcode

1. Go to **Products** page → Click **"Add Product"** button
2. In the "Manufacturer Barcode" field, you have two options:

   **Option A: Scan with USB/Bluetooth Handheld Scanner**
   - Focus on the barcode input field (click on it)
   - Scan the barcode printed on the product packaging
   - The scanner will automatically populate the field
   - No extra clicks needed

   **Option B: Scan with Webcam**
   - Click the **📷 Camera** button next to the barcode field
   - A camera dialog will open
   - Hold the product so the barcode is visible to the camera
   - The system will detect and read the barcode automatically
   - Click "Use Barcode" when successfully scanned

   **Option C: Manual Entry**
   - Manually type the barcode number into the field
   - Useful if scanning fails or barcode is damaged

### Step 2: Complete Product Information

- **Product Name**: (required) e.g., "Dona Maria Rice 5kg"
- **Manufacturer Barcode**: (optional but recommended) e.g., "8858266004237"
- **Category**: Select product category
- **Supplier**: Supplier name
- **Quantity**: Initial stock quantity (required)
- **Location**: Storage location (e.g., "Shelf A1")
- **Unit**: Unit of measure (e.g., "pack", "box", "unit")
- **Reorder Point**: Minimum stock level before reordering

### Step 3: Save Product

- Click **"Create Product"** button
- Product is now registered with its barcode as the unique identifier
- If you try to register another product with the same barcode, the system will prevent it and show: "This barcode already belongs to product: [Product Name]"

---

## Workflow: Receive Inventory

### Prerequisites

- Product must be registered first (with its barcode)
- Have the physical products with their barcodes available

### Steps

1. Go to **Receive Inventory** page
2. **Scan or Enter Barcode**:
   - Use handheld scanner (USB/Bluetooth) — focus on the barcode input field and scan
   - OR click **📷 Camera** button and scan with webcam
   - OR manually type the barcode and press Enter

3. **Product Lookup**:
   - **If Found**: Product details load immediately
     - Product name, category, current stock, unit of measure, stock status
     - Can adjust quantity to receive
     - Product is added to transaction list
   
   - **If Not Found**: Error message appears:
     - "Product not found. Please register this product first."
     - Go back to Products page to register the product
     - Then return to Receive Inventory

4. **Adjust Quantity**:
   - Use +/− buttons in transaction cart to change quantity
   - Click product row to view full details in right panel

5. **Add More Products**:
   - Keep scanning/entering more barcodes
   - Each product is added to the transaction list
   - See summary: "Total Products: X, Total Quantity: Y"

6. **Add Remarks** (Optional):
   - Enter notes about the shipment (supplier name, date received, etc.)

7. **Confirm Receive**:
   - Click **"Confirm Receive"** button
   - Stock quantities are updated in database
   - Transaction is recorded with date, user, barcode, and audit trail

---

## Workflow: Release Inventory

### Prerequisites

- Product must be registered first
- Product must have available stock

### Steps

1. Go to **Release Inventory** page
2. **Scan or Enter Barcode** (same as Receive):
   - Handheld scanner or webcam or manual entry
   - If not found: register product first

3. **Product Lookup**:
   - System checks product exists and has stock
   - **If stock available**: Product details load, add to transaction
   - **If out of stock**: Error message prevents transaction

4. **Adjust Quantity**:
   - Use +/− buttons to set quantity to release
   - Cannot release more than available stock

5. **Add More Products**: Repeat step 2-4 for multiple products

6. **Add Remarks** (Optional)

7. **Confirm Release**:
   - Click **"Confirm Release"** button
   - Stock quantities decrease
   - Transaction is recorded with full audit trail

---

## Using Barcodes for Search and Lookups

### Product Search

- Go to **Products** page
- Use search field to search by:
  - Product name
  - Barcode (exact match or partial match)
  - SKU (system-assigned identifier)
- Results show barcode column for verification

### Transaction History

- Go to **Inventory History** page
- View all transactions with:
  - Product name
  - Barcode used in transaction
  - Quantity moved
  - Transaction type (IN/OUT)
  - Date and user who performed transaction

### Stock Monitoring

- Barcode is displayed in product details
- Used as reference in all reports and transaction logs

---

## Barcode Format Support

IntelliWare supports these standard retail barcode formats:

| Format | Description | Example |
|--------|-------------|---------|
| EAN-13 | European retail barcodes | 5901234123457 |
| EAN-8  | Smaller packages | 96385074 |
| UPC-A  | North American retail | 042100005264 |
| UPC-E  | Compact UPC format | 0425261 |
| Code 128 | Industrial standard | Any alphanumeric |
| Code 39 | Warehouse systems | P-12345-ABC |
| Code 93 | Compact codes | Alphanumeric |
| ITF | Carton/pallet barcodes | 01234567890128 |

**Note**: The system handles barcode variants automatically:
- Automatically pads numeric barcodes to 13 digits when needed
- Converts 12-digit UPC-A to 13-digit EAN-13 by prepending 0
- Strips leading zeros if entered differently

---

## Troubleshooting

### "Product not found" Error

**Problem**: When scanning a barcode, system shows "Product not found. Please register this product first."

**Solution**:
1. Go to **Products** page
2. Click **"Add Product"**
3. Scan the same barcode in the form
4. Fill in product details
5. Click **"Create Product"**
6. Return to inventory transaction and scan again

### Duplicate Barcode Error

**Problem**: "This barcode already belongs to product: [Name]"

**Solution**:
- Each barcode can only belong to one product
- If you scanned the same product, you may have already registered it
- If it's a different product with the same barcode, verify the barcode is correct
- Check Products page for existing entry

### Camera Scanning Not Working

**Problem**: Webcam scanner not detecting barcodes

**Solutions**:
- Check browser camera permissions
- Ensure adequate lighting on barcode
- Try USB/Bluetooth handheld scanner instead
- Enter barcode manually if available
- Check that barcode is not damaged/unclear

### Manual Barcode Entry Tips

- Enter exactly as printed (including leading zeros)
- No spaces or special characters unless part of barcode
- Match case if alphanumeric (e.g., Code 39)
- System auto-detects numeric vs. alphanumeric formats

---

## Database Schema

### Products Table

Key barcode-related columns:

```sql
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    barcode VARCHAR(50) UNIQUE,  -- Manufacturer barcode (unique constraint)
    category VARCHAR(100),
    quantity INT DEFAULT 0,
    reorder_point INT DEFAULT 10,
    unit VARCHAR(50),
    ...
);
```

### Transactions Table

Barcode is recorded in each transaction for audit trail:

```sql
ALTER TABLE transactions ADD COLUMN barcode VARCHAR(50);
-- Records the barcode used when product was received/released
```

---

## Best Practices

1. **Always scan when possible** — More accurate than manual entry
2. **Use consistent barcode entry** — Helps with lookups and reporting
3. **Register before inventory moves** — Register products when received from supplier, before stock release
4. **Verify barcode accuracy** — Double-check physical barcode matches entered value
5. **Use appropriate scanner** — USB/Bluetooth scanners faster than camera for high-volume operations
6. **Check stock before release** — System prevents releasing out-of-stock items
7. **Add remarks for audits** — Helps track supplier, dates, conditions
8. **Review transaction history** — Track all barcode movements for reconciliation

---

## API Reference

### GET /api/get_product_by_barcode.php

Looks up a product by manufacturer barcode.

**Parameters:**
- `barcode` (string): The barcode to search for
- `code` (string): Alternative parameter name

**Response:**
```json
{
    "success": true,
    "found": true,
    "product": {
        "product_id": 123,
        "product_name": "Dona Maria Rice 5kg",
        "barcode": "8858266004237",
        "category": "Dry Goods",
        "quantity": 45,
        "reorder_point": 20,
        "unit": "pack",
        "stock_status": "in_stock",
        "image_url": "uploads/products/123.jpg",
        "sku": "P-0123"
    }
}
```

Or if not found:
```json
{
    "success": true,
    "found": false,
    "barcode": "8858266004237"
}
```

---

## Contact & Support

For issues with barcode scanning or product registration, contact your system administrator.

**Version**: 1.0  
**Last Updated**: 2026-06-30
