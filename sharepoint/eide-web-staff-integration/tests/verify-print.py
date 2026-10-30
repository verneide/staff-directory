"""Validate department pagination and the restricted fields in Chromium PDFs."""
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
        assert text.count(f'Employee {index:02}') == 1, (orientation, index)
    for excluded in ['Employee 36', 'View More Info', 'Save PDF', 'Choose Save as PDF', '@example.com', '605-555', '17 years', 'Confidential tag']:
        assert excluded not in text, excluded
    # The two small departments flow together; the following large one starts a page.
    assert 'Small Alpha' in texts[0] and 'Small Beta' in texts[0]
    assert 'Large Sales' not in texts[0]
    assert texts[1].strip().startswith('Large Sales'), texts[1]
    # Each continuation page containing Sales staff repeats its department header.
    for page in texts:
        if any(f'Employee {index:02}' in page for index in range(3, 33)):
            assert page.strip().startswith('Large Sales'), 'Missing continuation heading'
    assert 'Service Advisor' in text and 'Example Office' in text
    print(orientation, len(reader.pages), 'Letter pages and department headers verified')
