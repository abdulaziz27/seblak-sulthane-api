# Implementation Plan

- [x] 1. Create database migration for order notes field
  - Create migration file to add nullable notes field to orders table
  - Add notes field after order_type column with TEXT type
  - Ensure field is nullable and has default null value
  - _Requirements: 3.1, 3.2, 3.3_

- [x] 2. Update Order model to include notes field
  - Add 'notes' to the $fillable array in Order model
  - Ensure model can handle null values gracefully
  - _Requirements: 3.1, 3.2, 3.3_

- [x] 3. Enhance API ProductController for favorite product sorting
  - Modify index() method to sort by is_favorite DESC, name ASC
  - Ensure backward compatibility with existing API consumers
  - Test sorting logic with mixed favorite/non-favorite products
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 4. Verify and enhance product image handling in API
  - Check current image URL generation in API ProductController
  - Ensure consistent image path format between web and API
  - Add image_url field to API responses with full accessible URLs
  - Handle null/empty images gracefully in API responses
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 5. Update API OrderController to handle notes field
  - Modify saveOrder() method to accept optional notes parameter
  - Add notes validation (max length, optional field)
  - Include notes field in order response data
  - Ensure backward compatibility for requests without notes
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 6. Update order retrieval endpoints to include notes
  - Modify index() method in API OrderController to include notes
  - Modify summary() method to handle notes if needed
  - Ensure notes appear in all order-related API responses
  - _Requirements: 3.4, 3.6_

- [ ]* 7. Add validation tests for new functionality
  - Test order creation with and without notes
  - Test product sorting with favorite products
  - Test image URL generation in API responses
  - Test backward compatibility scenarios
  - _Requirements: 1.5, 2.5, 3.5_

- [x] 8. Run database migration and verify changes
  - Execute the migration in development environment
  - Verify notes field exists and accepts null values
  - Test that existing orders are not affected
  - _Requirements: 3.1, 3.2, 3.3_