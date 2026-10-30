"""Validate actual Chromium print output using synthetic fixtures."""
from pypdf import PdfReader
for orientation, expected in [('portrait', (612, 792)), ('landscape', (792, 612))]:
    reader = PdfReader('/tmp/ve-print-check/' + orientation + '.pdf')
    assert len(reader.pages) > 1, 'Fixture must exercise pagination'
    texts = []
    for page in reader.pages:
        assert abs(float(page.mediabox.width) - expected[0]) < 1
        assert abs(float(page.mediabox.height) - expected[1]) < 1
        texts.append(page.extract_text())
    text = '\n'.join(texts)
    for index in range(36):
        name, email = f'Employee {index:02}', f'person{index:02}@example.com'
        assert text.count(name) == 1, (orientation, name)
        assert any(name in page and email in page for page in texts), 'Card split or missing contact value'
    for excluded in ['Employee 36', 'View More Info', 'Save PDF', 'Choose Save as PDF']:
        assert excluded not in text, excluded
    print(orientation, len(reader.pages), 'Letter pages verified')
