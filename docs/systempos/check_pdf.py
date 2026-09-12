from pathlib import Path
import re
import pdfplumber
from PIL import Image, ImageOps, ImageDraw

root=Path(__file__).resolve().parents[2]
folder=root/'tmp/pdfs'
with pdfplumber.open(folder/'SystemPos.review.pdf') as pdf:
    texts=[]
    for i,page in enumerate(pdf.pages,1):
        words=[w for w in page.extract_words() if 40<float(w['top'])<795]
        bad=[w['text'] for w in words if w['x0']<40 or w['x1']>page.width-40]
        bottom=max(w['bottom'] for w in words)
        print(f'Page {i}: words={len(words)} bottom={bottom:.1f} outside={bad}')
        assert not bad,(i,bad)
        texts.append(page.extract_text())
    full='\n'.join(texts)
    for prefix,count in [('RF',54),('RN',14),('RNF',9),('AC',13),('D',8)]:
        for n in range(1,count+1):
            assert f'{prefix}-{n:02d}' in full,(prefix,n)
    assert 'http' not in full
    assert '\ufffd' not in full
    print('Identifiers, references exclusion and encoding: OK')
images=sorted(folder.glob('page-*.png'))
for start in range(0,len(images),4):
    sheet=Image.new('RGB',(1000,1450),'#ccd4d8')
    for j,path in enumerate(images[start:start+4]):
        im=Image.open(path).convert('RGB')
        im.thumbnail((486,690))
        x=7+(j%2)*500;y=25+(j//2)*725
        sheet.paste(im,(x,y))
        ImageDraw.Draw(sheet).text((x,y-18),path.stem,fill='black')
    sheet.save(folder/f'contact-{start//4+1}.png')
