#!/bin/bash

echo "Fixing namespaces from Cashkdiopen\Laravel to Cashkdiopen\Payments..."

# Files to process
find src -name "*.php" -type f > php_files.txt
find tests -name "*.php" -type f >> php_files.txt

# Process each file
while IFS= read -r file; do
    if [ -f "$file" ]; then
        echo "Processing: $file"
        
        # Create backup
        cp "$file" "$file.bak"
        
        # Replace namespace declarations
        sed 's/namespace Cashkdiopen\Laravel/namespace Cashkdiopen\Payments/g' "$file.bak" > "$file.tmp1"
        
        # Replace use statements - escape backslashes properly
        sed 's|use Cashkdiopen\Laravel\|use Cashkdiopen\Payments\|g' "$file.tmp1" > "$file.tmp2"
        
        # Replace route namespace references
        sed 's|Cashkdiopen\Laravel\Http\Controllers|Cashkdiopen\Payments\Http\Controllers|g' "$file.tmp2" > "$file.tmp3"
        
        # Replace event/listener references
        sed 's|Cashkdiopen\Laravel\Events\|Cashkdiopen\Payments\Events\|g' "$file.tmp3" > "$file.tmp4"
        sed 's|Cashkdiopen\Laravel\Listeners\|Cashkdiopen\Payments\Listeners\|g' "$file.tmp4" > "$file"
        
        # Cleanup temp files
        rm -f "$file.tmp1" "$file.tmp2" "$file.tmp3" "$file.tmp4"
        
        echo "Fixed: $file"
    fi
done < php_files.txt

# Cleanup
rm -f php_files.txt

echo "Namespace fixing completed!"
