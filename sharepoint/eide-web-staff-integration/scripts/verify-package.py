"""Verify the actual SPPKG before publishing it as a build artifact."""
import hashlib
import json
import os
from pathlib import Path
import posixpath
import re
from urllib.parse import unquote
import xml.etree.ElementTree as ET
import zipfile

project = Path(__file__).resolve().parents[1]
config = json.loads((project / "config/package-solution.json").read_text())
solution = config["solution"]
package = project / config["paths"].get("packageDir", "sharepoint") / config["paths"]["zippedPackage"]
ns = {"app": "http://schemas.microsoft.com/sharepoint/2012/app/manifest"}
with zipfile.ZipFile(package) as archive:
    assert archive.testzip() is None, "Package ZIP is corrupt"
    names = set(archive.namelist())
    assert {"AppManifest.xml", "[Content_Types].xml", "_rels/.rels"} <= names, "Missing package roots"
    manifest = ET.fromstring(archive.read("AppManifest.xml"))
    assert manifest.get("Name") == solution["name"], "Internal name mismatch"
    assert re.fullmatch(r"[A-Za-z][A-Za-z0-9._-]*", manifest.get("Name", "")), "Internal name must be a machine identifier, not a display title"
    assert manifest.get("Version") == solution["version"], "Packaged version mismatch"
    assert manifest.get("ProductID", "").strip("{}").lower() == solution["id"].lower(), "Solution identity changed"
    title = manifest.findtext("app:Properties/app:Title", namespaces=ns)
    assert title == solution.get("title", solution["name"]), "Display title mismatch"
    assert manifest.get("IsClientSideSolution") == "true", "Not a client-side solution"
    for name in names:
        assert not re.search(r"\s", name), "Whitespace in package part URI: " + name
        if name.endswith(".xml") or name.endswith(".rels"):
            element = ET.fromstring(archive.read(name))
            if name.endswith(".rels"):
                base = posixpath.dirname(posixpath.dirname(name))
                for rel in element:
                    if rel.get("TargetMode") == "External":
                        continue
                    target = unquote(rel.get("Target", ""))
                    resolved = target.lstrip("/") if target.startswith("/") else posixpath.normpath(posixpath.join(base, target))
                    assert resolved in names, "Missing relationship target: " + resolved
    icon = solution.get("iconPath")
    if icon:
        assert icon in names, "App icon missing from package"
        assert archive.read(icon).startswith(bytes.fromhex("89504e470d0a1a0a")), "Invalid PNG icon"
    assert any(name.startswith("ClientSideAssets/") and name.endswith(".js") for name in names), "Client bundle missing"
    report = {
        "file": package.name,
        "version": manifest.get("Version"),
        "internalName": manifest.get("Name"),
        "title": title,
        "productId": manifest.get("ProductID"),
        "sha256": hashlib.sha256(package.read_bytes()).hexdigest()
    }
report_text = json.dumps(report, indent=2)
print(report_text)
(package.parent / "package-verification.json").write_text(report_text + "\n")
if os.environ.get("GITHUB_OUTPUT"):
    with open(os.environ["GITHUB_OUTPUT"], "a") as output:
        output.write("version=" + report["version"] + "\n")
if os.environ.get("GITHUB_STEP_SUMMARY"):
    with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as summary:
        summary.write("### Verified SharePoint package\n\n```json\n" + report_text + "\n```\n")
