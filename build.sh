#!/bin/bash
# SMS Suite WHMCS - Build Clean Distributable Zip
# Usage: ./build.sh [version]

set -e

VERSION="${1:-1.0.0}"
BUILD_DIR="$(mktemp -d)"
OUTPUT_FILE="modules_v${VERSION}.zip"

echo "========================================"
echo "SMS Suite WHMCS - Build v${VERSION}"
echo "========================================"

# Create module directory structure
mkdir -p "${BUILD_DIR}/modules/addons/sms_suite"
mkdir -p "${BUILD_DIR}/modules/servers/sms_suite"

# Copy addon module files
echo "[1/5] Copying addon module files..."
cp -R modules/addons/sms_suite/* "${BUILD_DIR}/modules/addons/sms_suite/"

# Copy server module files (if they exist)
if [ -d "modules/servers/sms_suite" ]; then
    echo "[2/5] Copying server module files..."
    cp -R modules/servers/sms_suite/* "${BUILD_DIR}/modules/servers/sms_suite/"
else
    echo "[2/5] No server module directory found, skipping..."
    rmdir "${BUILD_DIR}/modules/servers/sms_suite"
    rmdir "${BUILD_DIR}/modules/servers" 2>/dev/null || true
fi

# Copy root-level API entry points
if [ -f "smsapi.php" ]; then
    cp smsapi.php "${BUILD_DIR}/"
fi
if [ -f "webhook.php" ]; then
    cp webhook.php "${BUILD_DIR}/"
fi

# Remove dev artifacts
echo "[3/5] Cleaning dev artifacts..."
find "${BUILD_DIR}" -name '.DS_Store' -delete 2>/dev/null || true
find "${BUILD_DIR}" -name '__MACOSX' -type d -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name '.git' -type d -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name '.gitignore' -delete 2>/dev/null || true
find "${BUILD_DIR}" -name '.claude' -type d -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name 'PROJECT_NOTES.md' -delete 2>/dev/null || true
find "${BUILD_DIR}" -name '*.log' -delete 2>/dev/null || true
find "${BUILD_DIR}" -name 'Thumbs.db' -delete 2>/dev/null || true

# Build zip
echo "[4/5] Creating ${OUTPUT_FILE}..."
cd "${BUILD_DIR}"
zip -r "${OLDPWD}/${OUTPUT_FILE}" . -x ".*"
cd "${OLDPWD}"

# Generate checksum
echo "[5/5] Generating checksum..."
SHA256=$(shasum -a 256 "${OUTPUT_FILE}" | awk '{print $1}')

# Cleanup
rm -rf "${BUILD_DIR}"

echo ""
echo "========================================"
echo "Build complete!"
echo "  File:     ${OUTPUT_FILE}"
echo "  Size:     $(du -h "${OUTPUT_FILE}" | awk '{print $1}')"
echo "  SHA256:   ${SHA256}"
echo "========================================"
echo ""
echo "${SHA256}  ${OUTPUT_FILE}" > "${OUTPUT_FILE}.sha256"
echo "Checksum saved to ${OUTPUT_FILE}.sha256"
