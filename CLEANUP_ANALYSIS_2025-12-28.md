# Project Cleanup Analysis - December 28, 2025
**Status:** ANALYSIS ONLY - NO FILES REMOVED YET  
**Purpose:** Comprehensive scan for obsolete, unused, and redundant code

---

## 📋 EXECUTIVE SUMMARY

### Categories Found:
1. **Previously Identified Issues** (from OBSOLETE_FILES_ANALYSIS.md)
2. **New Findings** (this scan)
3. **Temporary/Development Files**
4. **Unused Routes & Endpoints**
5. **Debug Code in Production**

### Total Items: ~25 issues identified

---

## 🔴 CRITICAL PRIORITY - REMOVE IMMEDIATELY

### 1. Test/Debug Files (Root Level)
- **`test_lease_endpoint.php`** (root directory)
  - Appears to be development/testing file
  - Not referenced in any production code
  - **Action:** DELETE

- **`.DS_Store`** (root directory)
  - macOS system file
  - Should be in .gitignore
  - **Action:** DELETE and add to .gitignore

### 2. Unused Route File
- **`routes/api.php`** 
  - Per routes/README.md: "All API routes are now defined in /index.php"
  - File is not currently used
  - **Action:** DELETE or document if keeping for reference

### 3. Old Directory Structure (Previously Identified)
- **`/kea-api-admin/`** entire subdirectory
  - Contains old/duplicate structure
  - Has duplicate routes/api.php with older definitions
  - Old README describing outdated structure
  - **Action:** DELETE entire directory

---

## 🟡 HIGH PRIORITY - REVIEW & CLEAN

### 4. Root Level Duplicate Files
- **`leases.php`** (root)
  - Duplicate of `/views/dhcp/leases.php`
  - Old route in index.php line 329
  - New route in index.php line 333
  - **Action:** DELETE root version, update any remaining references

### 5. Development Artifacts
- **`parser.txt`** (root)
  - Contains JSON option-def snippets
  - No code references found
  - **Action:** DELETE (archive first if needed)

### 6. One-Time Migration Scripts
Located in `/scripts/` directory:
- **`fix_bvi_interface_numbers.sql`**
  - One-time database migration
  - **Action:** ARCHIVE to `/backups/migration_scripts/` or DELETE

- **`fix_dhcp_subnet.sql`**
  - One-time database migration
  - **Action:** ARCHIVE to `/backups/migration_scripts/` or DELETE

- **`fix_dhcp_subnet.php`**
  - One-time fix script
  - **Action:** ARCHIVE to `/backups/migration_scripts/` or DELETE

---

## 🟠 MEDIUM PRIORITY - CONSOLIDATE

### 7. Duplicate Docker Documentation
- **`DOCKER.md`** (349 lines)
- **`DOCKER-DEPLOYMENT.md`** (338 lines)
  - Significant content overlap
  - Both cover deployment, setup, troubleshooting
  - **Action:** Merge into single comprehensive guide

### 8. RADIUS Documentation (3 separate files)
- **`docs/RADIUS_INTEGRATION.md`** - Integration guide
- **`docs/RADIUS_SETUP.md`** - Setup guide
- **`scripts/RADIUS_SCRIPTS_README.md`** - Scripts documentation
  - Could be consolidated
  - Each serves slightly different purpose but has overlap
  - **Action:** Consider consolidating into 2 files max

---

## 🟢 LOW PRIORITY - MONITOR

### 9. Debug Code in Production Files

**File:** `views/layout.php` (lines 8-12)
```php
// Debug the existing auth
error_log('Existing auth in GLOBALS: ' . print_r(isset($GLOBALS['auth']), true));
error_log('New auth instance created');
error_log('isAdmin result: ' . ($auth->isAdmin() ? 'true' : 'false'));
```
- **Action:** Remove or wrap in `if (getenv('APP_ENV') === 'development')`

**File:** `views/login.php` (lines 44-52)
```php
<?php if (getenv('APP_ENV') === 'development'): ?>
<div class="bg-yellow-100 p-4 mb-4">
    <h3 class="font-bold">Debug Information:</h3>
    <pre class="text-xs">
    Session ID: <?php echo session_id(); ?>
    ...
```
- **Status:** ACCEPTABLE - Already wrapped in development check
- **Action:** KEEP as-is (good practice)

### 10. Commented Out Code Blocks
**File:** `config/kea.php`
```php
// Legacy fallback servers disabled - use /admin/kea-servers to configure
'servers' => [],
```
- **Status:** ACCEPTABLE - Legacy config documented
- **Action:** KEEP for now (shows migration path)

**File:** Multiple controller files
- Extensive error_log() calls throughout
- Example: `src/Controllers/Api/DHCPController.php` lines 277+
- **Action:** Consider adding log level control (development vs production)

---

## 🔵 INFORMATIONAL - NO ACTION NEEDED

### 11. Files Already Cleaned Up
Per OBSOLETE_FILES_ANALYSIS.md, these were mentioned but don't exist:
- ✅ `dhcp6.leases.csv` - Not found (already cleaned)
- ✅ `DATABASE_FIXES_REPORT.md` - Not found (already cleaned)
- ✅ `I_FUCKED_UP_HERE.md` - Not found (already cleaned)
- ✅ `info.php` - Not found (already cleaned)
- ✅ `test_auth.php` - Not found (already cleaned)
- ✅ `test.php` - Not found (already cleaned)

### 12. Legitimate Placeholders
- **`/backups/`** directory
  - Contains only .gitignore and .gitkeep
  - **Status:** Intentional placeholder
  - **Action:** KEEP

---

## 📊 DETAILED FILE INVENTORY

### Confirmed Active & Necessary Files:
All files in these directories appear to be in active use:
- ✅ `/src/Controllers/Api/` - All API controllers active
- ✅ `/src/Models/` - All models active
- ✅ `/views/` - All view files active
- ✅ `/database/` - Schema and migrations active
- ✅ `/scripts/` - Active scripts (except 3 one-time migrations noted above)
  - `create_admin.php` ✅
  - `import_kea_config.php` ✅
  - `check_radius_orphans.php` ✅
  - `clean_radius_orphans.php` ✅
  - `sync_*.php` scripts ✅
  - `setup_*.sh` scripts ✅

### Swagger/OpenAPI Documentation:
- ✅ `swagger.json` - Main API docs (active)
- ✅ `swagger-admin.json` - Admin API docs (active)
- **Status:** Both needed, serve different purposes

---

## 🎯 RECOMMENDED ACTION PLAN

### Phase 1: Safe Deletions (Can be done immediately)
```bash
# Delete test/debug files
rm test_lease_endpoint.php
rm .DS_Store

# Add to .gitignore
echo ".DS_Store" >> .gitignore

# Delete old directory structure
rm -rf kea-api-admin/

# Delete development artifacts
rm parser.txt
```

### Phase 2: Remove Duplicates (Review first)
```bash
# Verify leases.php not referenced
grep -r "leases.php" . --exclude-dir=node_modules --exclude-dir=vendor
# Then delete root version
rm leases.php

# Delete unused routes file (verify first)
rm routes/api.php
```

### Phase 3: Archive Migration Scripts
```bash
# Create archive directory
mkdir -p backups/migration_scripts_2025

# Move one-time scripts
mv scripts/fix_bvi_interface_numbers.sql backups/migration_scripts_2025/
mv scripts/fix_dhcp_subnet.sql backups/migration_scripts_2025/
mv scripts/fix_dhcp_subnet.php backups/migration_scripts_2025/
```

### Phase 4: Documentation Consolidation
1. Merge DOCKER.md and DOCKER-DEPLOYMENT.md
2. Consider consolidating RADIUS documentation

### Phase 5: Code Cleanup
1. Remove debug error_log from views/layout.php lines 8-12
2. Review and potentially reduce error_log verbosity in production

---

## ⚠️ SAFETY CHECKLIST

Before removing ANY file:
- [ ] Search entire codebase for references
- [ ] Check if referenced in any documentation
- [ ] Verify no routes point to the file
- [ ] Review git history to understand purpose
- [ ] Test in development environment
- [ ] Create backup/archive before deletion

---

## 📈 IMPACT SUMMARY

### Estimated Space Savings:
- Test files: ~5 KB
- Old directory: ~50 KB
- Artifacts: ~2 KB
- Migration scripts (archived): ~15 KB
- **Total:** ~72 KB direct cleanup

### Code Maintainability Benefits:
- Reduced confusion from duplicate files
- Cleaner project structure
- Easier onboarding for new developers
- Less cognitive overhead when navigating codebase

### Risk Assessment:
- **LOW RISK:** Test files, .DS_Store, parser.txt
- **MEDIUM RISK:** Duplicate files (verify usage first)
- **HIGH RISK:** Migration scripts (archive, don't delete)
- **REVIEW NEEDED:** Documentation consolidation

---

## 🔍 VERIFICATION COMMANDS

To verify these files are safe to remove:

```bash
# Check for references to test file
grep -r "test_lease_endpoint" . --exclude-dir=vendor --exclude-dir=node_modules

# Check for references to leases.php
grep -r "require.*leases\.php\|include.*leases\.php" . --exclude-dir=vendor

# Check routes/api.php usage
grep -r "routes/api\.php\|routes\/api\.php" . --exclude-dir=vendor

# Check parser.txt references
grep -r "parser\.txt" . --exclude-dir=vendor

# Check old kea-api-admin directory references
grep -r "kea-api-admin/" . --exclude-dir=vendor --exclude-dir=node_modules
```

---

## 📝 NOTES

- This analysis supplements the existing OBSOLETE_FILES_ANALYSIS.md from 2025-12-17
- Some items overlap between analyses (confirms earlier findings)
- No files have been modified during this scan
- All recommendations should be verified in development before production

**Last Updated:** December 28, 2025  
**Analyzed By:** AI Code Scan  
**Next Review:** After cleanup completion
