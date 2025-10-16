# Design Document

## Overview

This design document outlines the implementation approach for three POS system enhancements: product image handling verification and fixes, favorite product prioritization, and optional order notes functionality. The solution focuses on minimal changes to existing production code while ensuring backward compatibility.

## Architecture

The enhancements will be implemented across three main layers:

1. **Database Layer**: Add missing fields and ensure proper indexing
2. **API Layer**: Update controllers to handle new functionality
3. **Storage Layer**: Verify and fix image storage handling

## Components and Interfaces

### 1. Product Image Enhancement

**Current State Analysis:**
- Web ProductController already handles image upload in `store()` and `update()` methods
- API ProductController has image handling but may need path verification
- Images stored in `storage/products/` directory

**Design Changes:**
- Verify image path generation consistency between web and API
- Ensure API returns full accessible URLs (not just storage paths)
- Add image URL transformation in API responses

**API Response Format:**
```json
{
  "id": 1,
  "name": "Product Name",
  "image": "https://domain.com/storage/products/1.jpg",
  "image_url": "https://domain.com/storage/products/1.jpg"
}
```

### 2. Favorite Product Prioritization

**Database Design:**
- Existing `is_favorite` field in products table (boolean)
- No additional fields needed

**API Query Modification:**
```sql
ORDER BY is_favorite DESC, name ASC
```

**Implementation Points:**
- Update API ProductController `index()` method
- Maintain backward compatibility
- Ensure consistent sorting across all product endpoints

### 3. Order Notes Functionality

**Database Design:**
- Add `notes` field to `orders` table
- Field specifications:
  - Type: TEXT
  - Nullable: true
  - Default: null

**Migration Structure:**
```php
Schema::table('orders', function (Blueprint $table) {
    $table->text('notes')->nullable()->after('order_type');
});
```

**Model Updates:**
- Add `notes` to Order model `$fillable` array
- No additional relationships needed

**API Integration:**
- Update `saveOrder()` method validation (optional field)
- Include notes in order responses
- Maintain backward compatibility for existing mobile apps

## Data Models

### Updated Order Model
```php
protected $fillable = [
    'payment_amount',
    'sub_total',
    'tax',
    'discount',
    'discount_amount',
    'service_charge',
    'total',
    'payment_method',
    'total_item',
    'id_kasir',
    'nama_kasir',
    'transaction_time',
    'outlet_id',
    'member_id',
    'order_type',
    'qris_fee',
    'notes'  // New field
];
```

### Product API Response Enhancement
```php
// Ensure consistent image URL format
$products = $products->map(function ($product) {
    if ($product->image) {
        $product->image_url = asset($product->image);
    } else {
        $product->image_url = null;
    }
    return $product;
});
```

## Error Handling

### Image Upload Errors
- File type validation (png, jpg, jpeg)
- File size limits (existing validation)
- Storage permission errors
- Missing image graceful handling

### Order Notes Errors
- Text length validation (max 1000 characters)
- Special character handling
- Null value acceptance

### Favorite Product Errors
- Invalid boolean values
- Database constraint violations

## Testing Strategy

### Image Functionality Tests
1. Upload image via web form - verify storage location
2. Retrieve product via API - verify image URL format
3. Update product image - verify old image cleanup
4. Handle missing images - verify graceful degradation

### Favorite Product Tests
1. Mark product as favorite - verify API sorting
2. Multiple favorites - verify alphabetical sub-sorting
3. No favorites - verify default alphabetical sorting
4. Mixed favorite/non-favorite - verify correct ordering

### Order Notes Tests
1. Create order with notes - verify storage
2. Create order without notes - verify null handling
3. Retrieve order - verify notes in response
4. Long notes - verify length validation

## Implementation Considerations

### Production Safety
- All changes maintain backward compatibility
- Database migration is additive only (no data loss)
- API responses include new fields without breaking existing clients
- Graceful handling of null/missing values

### Performance Impact
- Favorite sorting adds minimal query overhead
- Image URL generation is lightweight
- Notes field addition has negligible storage impact

### Rollback Strategy
- Database migration can be rolled back safely
- API changes are backward compatible
- No breaking changes to existing functionality