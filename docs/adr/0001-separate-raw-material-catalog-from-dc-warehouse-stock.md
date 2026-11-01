# Separate Raw Material Master Catalog from DC Warehouse Stock

Previously, the Distribution Center (DC) role possessed routes and forms to create and modify Raw Materials alongside recording warehouse stock. We restricted Raw Material master catalog management exclusively to Admin and scoped DC strictly to managing physical warehouse stock (`StokDc`) and inbound replenishment (`addStock`). This preserves recipe integrity across outlets, enforces single source of truth for global master data, and prevents operational warehouse staff from altering company-wide catalog definitions.
