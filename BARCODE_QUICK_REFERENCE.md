# Quick Reference: Barcode Scanning in IntelliWare

## 🔑 Key Points

- **Use manufacturer barcodes** printed on product packaging
- **Barcodes are product identifiers** — each product has one unique barcode
- **Register products first** before inventory operations
- **Three scanning methods**: Handheld scanner, webcam, or manual entry

---

## 📦 Product Registration

```
Products page → Add Product → 
Scan barcode (or enter manually) → 
Fill details → Create
```

**Result**: Barcode becomes the unique product identifier

---

## 📥 Receive Inventory

```
Receive page → Scan barcode → 
Product loads automatically → 
Adjust quantity → Add to transaction → 
Scan more or Confirm Receive
```

**If barcode not found**: "Product not found. Please register this product first."

---

## 📤 Release Inventory

```
Release page → Scan barcode → 
Product loads (checks stock) → 
Adjust quantity (cannot exceed available) → 
Add to transaction → Scan more or Confirm Release
```

**Prevents release of out-of-stock products**

---

## 🎯 Scanning Methods

| Method | How | When to Use |
|--------|-----|------------|
| **Handheld Scanner** | Focus field → Scan | High volume, accuracy |
| **Webcam (📷)** | Click button → Scan barcode at camera | Mobile, quick single scan |
| **Manual Entry** | Type barcode → Press Enter | Damaged barcode, unavailable |

---

## ❌ Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| "Product not found" | Product not registered | Go to Products, register it, then retry |
| "Duplicate barcode" | Barcode exists for another product | Verify barcode is correct, or edit existing product |
| Camera not working | Permissions or hardware issue | Try handheld scanner or manual entry |

---

## ✅ Important Reminders

- ✅ System READS barcodes from packaging
- ✅ Barcodes IDENTIFY products uniquely
- ❌ System CANNOT generate barcodes
- ❌ System CANNOT print barcodes
- ❌ System CANNOT assign new barcodes automatically

---

## 📚 Full Documentation

See `BARCODE_MANAGEMENT.md` for complete guide with workflows, troubleshooting, and best practices.
