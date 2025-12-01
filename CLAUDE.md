# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WeRelate.org is a genealogical wiki built on MediaWiki. The codebase extends MediaWiki with custom structured namespaces for genealogical data (Person, Family, Place, Source, etc.) and specialized functionality for family tree visualization, GEDCOM import/export, and genealogical data management.

## Setup and Installation

### Database Setup
```bash
# Download and import skeleton database
wget http://public.werelate.org.s3.amazonaws.com/wikidb.sql
mysql -u [user] -p [database_name] < wikidb.sql
```

### Simple Setup
```bash
cp htaccess.sample .htaccess
# Edit environment variables in .htaccess
```

### Full Setup
1. Copy files to a `w` directory under your htdocs root
2. Create Apache site file from `conf/apache2.sample`
3. Copy to `apache2/sites-available/[sitename]`
4. Fill in/modify values based on your environment
5. Enable the site: `a2ensite [sitename]`

### Running Jobs
Background jobs are disabled in the web context (`$wgJobRunRate = 0`). Run jobs manually:
```bash
php maintenance/runJobs.php
```

## Architecture

### Core MediaWiki
- **Entry point**: `index.php` - Main wiki script that initializes MediaWiki and dispatches requests
- **Configuration**: `LocalSettings.php` - Site configuration (uses environment variables from .htaccess or Apache config)
- **Core includes**: `includes/` - MediaWiki core files (Article, Parser, Database, etc.)

### Custom Namespaces (Structured Data)
Located in `extensions/structuredNamespaces/`:

All structured namespaces extend `StructuredData.php` which provides:
- XML parsing and validation
- Edit form rendering and data import
- Propagation of edits across linked pages
- Data quality checking via `DQHandler.php`

**Note**: Extension loading order in LocalSettings.php matters. Source and UserPage must be loaded before Name and Place because they contain names and places.

**Namespace Constants** (defined in LocalSettings.php):
| Namespace | Constant | ID |
|-----------|----------|-----|
| Givenname | NS_GIVEN_NAME | 100 |
| Surname | NS_SURNAME | 102 |
| Source | NS_SOURCE | 104 |
| Place | NS_PLACE | 106 |
| Person | NS_PERSON | 108 |
| Family | NS_FAMILY | 110 |
| MySource | NS_MYSOURCE | 112 |
| Repository | NS_REPOSITORY | 114 |
| Portal | NS_PORTAL | 116 |
| Transcript | NS_TRANSCRIPT | 118 |

**Key structured namespace classes:**
- `Person.php` - Individual person pages with birth/death/events
- `Family.php` - Family pages linking spouses and children
- `Place.php` - Geographic locations with coordinates
- `Source.php` - Citations and sources
- `Name.php` - Handles Givenname and Surname namespaces
- `MySource.php` - User-specific sources
- `Repository.php` - Archive/repository information
- `Transcript.php` - Historical document transcriptions
- `UserPage.php` - Enhanced user pages
- `ArticlePage.php` - Enhanced article pages
- `SDImage.php` - Image handling with structured data

**Supporting infrastructure:**
- `ESINHandler.php` - Event, Source, Image, and Name data structure handling
- `PropagationManager.php` - Manages cascading edits when pages are moved/deleted/updated
- `DateHandler.php` - Genealogical date parsing and validation
- `DQHandler.php` - Data quality validation
- `TipManager.php` - Contextual help/tips for edit forms

### Family Tree Features
Located in `extensions/familytree/`:
- `FamilyTree.php` - Core family tree data structure and visualization
- `FamilyTreePropagator.php` - Updates family tree when pages change
- `SpecialShowFamilyTree.php` - Family tree visualization page
- `SpecialTreeRelated.php` - Find related people across trees
- `SpecialTreeDeletionImpact.php` - Analyze impact before deleting pages
- `SpecialTreeCountWatchers.php` - Track page watchers
- `SpecialCopyTree.php` - Copy family tree branches

### GEDCOM Import/Export
Located in `extensions/gedcom/`:
- `SpecialGedcomPage.php` - GEDCOM file upload and processing
- `SpecialGedcoms.php` - List and manage GEDCOM imports
- `GedcomExportJob.php` - Background job for GEDCOM export
- `GedcomAjaxFunctions.php` - AJAX handlers for GEDCOM operations

GEDCOM directories (configured in LocalSettings.php):
- `$wrGedcomUploadDirectory` - Uploaded GEDCOM files
- `$wrGedcomInprocessDirectory` - Files being processed
- `$wrGedcomXMLDirectory` - Parsed XML data
- `$wrGedcomExportDirectory` - Generated exports
- `$wrGedcomArchiveDirectory` - Archived files

### Special Pages
Located in `extensions/other/`:
- `SpecialSearch.php` - Custom search (uses external search service)
- `SpecialDashboard.php` - User dashboard
- `SpecialBrowse.php` - Browse pages by namespace
- `SpecialAddPage.php` - Add new pages with validation
- `SpecialCompare.php` - Compare pages for merging
- `SpecialMerge.php` - Merge duplicate pages
- `SpecialShowDuplicates.php` - Find potential duplicates
- `SpecialReviewMerge.php` - Review merge suggestions
- `SpecialDataQuality.php` - Data quality reports
- `SpecialDQStats.php` - Data quality statistics
- `SpecialTrees.php` - Tree management
- `SpecialNetwork.php` - Social network features
- `SpecialRequestDelete.php` - Request page deletion
- `SpecialShowPedigree.php` - Pedigree chart visualization
- `SpecialPlaceMap.php` - Map visualization of places

### AJAX Handlers
- `includes/AjaxDispatcher.php` - Routes AJAX requests
- `extensions/other/IndexAjaxFunctions.php` - Index page AJAX
- `extensions/other/ListAjaxFunctions.php` - List page AJAX
- `extensions/other/MiscAjaxFunctions.php` - Miscellaneous AJAX
- `extensions/familytree/FamilyTreeAjaxFunctions.php` - Family tree AJAX
- `extensions/gedcom/GedcomAjaxFunctions.php` - GEDCOM AJAX

### Frontend JavaScript
Key JavaScript files in root directory:
- `personfamily.js` - Person and Family page interactions
- `search.js` - Search interface
- `autocomplete.js` - Autocomplete functionality
- `compare.js` - Page comparison UI
- `merge.js` - Merge interface
- `placemap.js` - Place map interactions
- `pedigreemap.js` - Pedigree map
- `familytree.js` - Family tree visualization
- Multiple jQuery plugins for UI components

### External Services
Configured via environment variables:
- **Search**: External search service at `$wrSearchHost:$wrSearchPort/$wrSearchPath` (separate "search" project)
- **Place Search**: Separate endpoint `$wrPlaceSearchHost:$wrPlaceSearchPort` (used for place autocomplete)
- **Memcached**: Optional caching at `$wgMemCachedServers`

### Custom Parser Tags
Registered in `extensions/other/Hooks.php`:
- `<wr_img>` - Image embedding
- `<youtube>` - YouTube video embedding
- `<googlemap>` - Google Maps embedding
- `<addsubpage>` / `<listsubpages>` - Subpage management
- `<wr_ad>` / `<mh_ad>` - Advertisement placeholders
- `<person_count>` - Display person statistics

## Key Conventions

### MediaWiki Hooks
Structured namespace classes register hooks to intercept MediaWiki lifecycle events:
- `ArticleEditShow` - Render custom edit forms
- `ImportEditFormDataComplete` - Import data from form submission
- `EditFilter` - Validate before saving
- `ArticleSave` - Propagate changes after save
- `TitleMoveComplete` - Handle page moves
- `ArticleDeleteComplete` - Handle deletions
- `ArticleUndeleteComplete` - Handle undeletions
- `ArticleRollbackComplete` - Handle rollbacks

### Structured Data XML Format
Pages in structured namespaces embed XML within wiki markup:
```xml
<person>
  <name>...</name>
  <gender>...</gender>
  <event_fact>...</event_fact>
  ...
</person>
```

The XML is parsed by the respective namespace class (e.g., Person, Family) and rendered as structured infoboxes.

### Propagation Pattern
When a Person/Family/Place page is edited, moved, or deleted, the change propagates to linked pages:
1. Hook detects the change
2. `PropagationManager` identifies affected pages
3. Affected pages are updated with `PROPAGATE_EDIT_FLAGS` (minor bot edit)

### Data Quality System
- `DQHandler.php` validates structured data during save
- `SpecialDataQuality.php` generates reports of quality issues
- Issues include: missing dates, invalid places, broken links, formatting problems

### Search Architecture
WeRelate uses an external search service (not built-in MediaWiki search):
- `$wgDisableTextSearch = true` disables MediaWiki search
- `SpecialSearch.php` queries external service via HTTP at `$wrSearchHost:$wrSearchPort$wrSearchPath`
- Place search uses separate endpoint for geographic queries and autocomplete

## Development Workflow

### Testing Changes
No automated test suite is present. Manual testing required:
1. Edit files
2. Clear cache if using memcached
3. Test in browser

### Debugging
Debug logging configured in LocalSettings.php:
- `$wgDebugLogFile` - General debug log
- `$wgRateLimitLog` - Rate limit violations

### Related Projects
WeRelate is part of a larger ecosystem:
- **search project** - Search indexing and query service
- **werelate-gedcom project** - GEDCOM parsing
- **gedcom-review project** - GEDCOM review interface
- **wikidata project** - Data parsing and batch update scripts
- **Family Tree Explorer** - Being deprecated; source code not available

## Important Notes

- This is a modified MediaWiki installation (appears to be based on an older version)
- Jobs must be run via command line (`maintenance/runJobs.php`), not web requests
- Anonymous users cannot edit (`$wgGroupPermissions['*']['edit'] = false`)
- Email confirmation required to edit (`$wgEmailConfirmToEdit = true`)
- Rate limiting configured for nights and days differently (`$wrNightBegin`, `$wrNightEnd`)
- Tidy HTML cleanup is disabled (`$wgUseTidy = false`)
