# Potentially Obsolete/Duplicate Files Analysis
**Date:** 2025-12-17  
**Status:** ANALYSIS ONLY - NO FILES REMOVED

## ⚠️ CRITICAL: Files Likely Obsolete

### 1. Duplicate/Old Directory Structure
- **`/kea-api-admin/`** - Entire subdirectory appears to be old/duplicate structure
  - Contains old README.md describing outdated structure
  - Has duplicate routes/api.php with older endpoint definitions
  - Contains single layout.php view (minimal compared to /views/)
  - Appears to be an old version before restructuring
  - **Impact:** HIGH - Likely completely unused, creates confusion

### 2. Root-Level Obsolete Files
- **`leases.php`** - Duplicate of `/views/dhcp/leases.php`
  - Both files exist and serve same purpose
  - Root version referenced in index.php line 329 (old route)
  - Views version referenced in index.php line 333 (new route)
  - **Impact:** MEDIUM - One should be removed, likely the root version

- **`parser.txt`** - Configuration snippet file
  - Contains JSON option-def snippets
  - No references found in codebase
  - Appears to be development/testing artifact
  - **Impact:** LOW - Likely safe to remove

## 📄 Documentation Files - Potential Duplicates

### 1. Docker Documentation
- **`DOCKER.md`** - Comprehensive Docker deployment guide (349 lines)
- **`DOCKER-DEPLOYMENT.md`** - Also Docker deployment guide (338 lines)
  - **Analysis:** Significant overlap in content
  - Both cover deployment, setup, troubleshooting
  - Should be consolidated into single source of truth
  - **Impact:** MEDIUM - Creates confusion, maintenance overhead

### 2. RADIUS Documentation
- **`docs/RADIUS_INTEGRATION.md`** - Integration guide
- **`docs/RADIUS_SETUP.md`** - Setup guide  
- **`scripts/RADIUS_SCRIPTS_README.md`** - Scripts documentation
  - **Analysis:** Three separate docs for RADIUS
  - Could potentially be consolidated
  - Each serves slightly different purpose but overlap exists
  - **Impact:** LOW-MEDIUM - Could be better organized

## 🔧 Routes & API Concerns

### 1. Deprecated API Endpoints (Per docs/api.md)
The following endpoints are documented as deprecated:
- Generic `/api/subnets` endpoints (marked as "non-functional")
- Should migrate to `/api/dhcp/subnets` or `/api/ipv6/subnets`

### 2. Dual Route Files
- **`/routes/api.php`** - Main API routes (active, 450+ lines)
- **`/kea-api-admin/routes/api.php`** - Old API routes (32 lines, different structure)
  - **Impact:** HIGH - Confusion about which is authoritative

## 🗃️ Database Migration Files

### Scripts Directory Analysis
All files appear to be in use or serve specific purposes:
- ✅ `create_admin.php` - Active utility
- ✅ `import_kea_config.php` - Active import tool
- ✅ `fix_dhcp_subnet.php/sql` - Maintenance scripts (may be one-time use)
- ✅ `check_radius_orphans.php` - Active maintenance
- ✅ `clean_radius_orphans.php` - Active maintenance
- ✅ `setup_radius.sh` - Setup script
- ✅ Various sync scripts - Active synchronization tools

**Note:** `fix_bvi_interface_numbers.sql` and `fix_dhcp_subnet.sql` may be one-time migration scripts that could be archived.

## 📦 Swagger/OpenAPI Files

- **`swagger.json`** - Main API documentation (249 lines)
- **`swagger-admin.json`** - Admin API documentation (518 lines)
  - **Analysis:** Both appear active and serve different purposes
  - Main swagger for public API, admin for administrative endpoints
  - **Impact:** NONE - Both needed

## 🚫 Files NOT Found (Referenced in workspace structure but missing)

These files were mentioned in initial workspace context but don't exist:
- `dhcp6.leases.csv` - Not found
- `DATABASE_FIXES_REPORT.md` - Not found  
- `I_FUCKED_UP_HERE.md` - Not found
- `info.php` - Not found
- `test_auth.php` - Not found (mentioned in kea-api-admin README)
- `test.php` - Not found

**Impact:** These may have been cleaned up already or were temporary files

## 💾 Empty/Placeholder Directories

- **`/backups/`** - Contains only .gitignore and .gitkeep
  - **Impact:** NONE - Intentional placeholder for backup files

## 📋 Recommendations Summary

### HIGH Priority (Should Review/Remove)
1. **`/kea-api-admin/`** directory - Appears to be complete duplicate/old structure
2. **`leases.php`** (root) - Duplicate of views/dhcp/leases.php
3. Consolidate DOCKER.md and DOCKER-DEPLOYMENT.md

### MEDIUM Priority (Consider Removing/Archiving)
1. **`parser.txt`** - No code references, appears to be dev artifact
2. `fix_bvi_interface_numbers.sql` - Likely one-time migration
3. `fix_dhcp_subnet.sql` - Likely one-time migration
4. Consider consolidating RADIUS documentation

### LOW Priority (Keep but Monitor)
1. Multiple README files in different directories
2. Script documentation files (active but could be consolidated)

## 🔍 Verification Steps Before Removal

Before removing ANY file:
1. ✅ Search codebase for all references (require, include, file paths)
2. ✅ Check if file is referenced in documentation
3. ✅ Verify no routes point to the file
4. ✅ Check git history to understand why file was created
5. ✅ Test application after removal in development environment
6. ✅ Create backup before deletion

## 📊 Statistics

- Total potentially obsolete files: ~15
- Total duplicate documentation: 3-4 files
- Entire obsolete directory: 1 (`/kea-api-admin/`)
- Development artifacts: 1 (`parser.txt`)
- Duplicate PHP files: 1 (`leases.php`)
- One-time migration scripts: 2-3

---
**IMPORTANT:** This is an analysis only. NO files have been modified or removed during this scan.
