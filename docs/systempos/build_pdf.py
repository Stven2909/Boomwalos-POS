from pathlib import Path
import re
from xml.sax.saxutils import escape
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.pagesizes import A4

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'tmp' / 'pdfs'
OUT.mkdir(parents=True, exist_ok=True)
FONT = Path('C:/Windows/Fonts')
for name, filename in [('Body','calibri.ttf'),('Bold','calibrib.ttf'),('Italic','calibrii.ttf')]:
    pdfmetrics.registerFont(TTFont(name, str(FONT / filename)))
pdfmetrics.registerFontFamily('Body',normal='Body',bold='Bold',italic='Italic',boldItalic='Bold')
INK=colors.HexColor('#173044')
TEAL=colors.HexColor('#166D68')
MUTED=colors.HexColor('#4B5C68')
PALE=colors.HexColor('#EFF5F5')
LINE=colors.HexColor('#D4DFE2')
AMBER=colors.HexColor('#D99A32')
WIDTH=A4[0]-84
styles={
 'body':ParagraphStyle('body',fontName='Body',fontSize=10.5,leading=13.8,textColor=INK,spaceAfter=7,allowWidows=0,allowOrphans=0),
 'h1':ParagraphStyle('h1',fontName='Bold',fontSize=38,leading=43,textColor=INK,spaceAfter=5,keepWithNext=True),
 'h2':ParagraphStyle('h2',fontName='Bold',fontSize=17,leading=21,textColor=TEAL,spaceBefore=14,spaceAfter=9,keepWithNext=True),
 'h3':ParagraphStyle('h3',fontName='Bold',fontSize=12.2,leading=16,textColor=INK,spaceBefore=9,spaceAfter=5,keepWithNext=True),
 'cell':ParagraphStyle('cell',fontName='Body',fontSize=9.3,leading=12.2,textColor=INK,spaceAfter=0),
 'th':ParagraphStyle('th',fontName='Bold',fontSize=9.3,leading=12.2,textColor=colors.white,spaceAfter=0),
 'meta':ParagraphStyle('meta',fontName='Bold',fontSize=10,leading=14,textColor=MUTED,spaceAfter=13),
}

def p(text, kind='body'):
    text=escape(text)
    text=re.sub(r'^(RF-\d+\.|RN-\d+\.)',r'<b>\1</b>',text)
    return Paragraph(text,styles[kind])

class NumberedCanvas(canvas.Canvas):
    def __init__(self,*a,**kw):
        super().__init__(*a,**kw)
        self.states=[]
    def showPage(self):
        self.states.append(dict(self.__dict__))
        self._startPage()
    def save(self):
        total=len(self.states)
        for state in self.states:
            self.__dict__.update(state)
            self.saveState()
            self.setFillColor(INK)
            self.rect(0,A4[1]-30,A4[0],30,fill=1,stroke=0)
            self.setFillColor(colors.white)
            self.setFont('Bold',9)
            self.drawString(42,A4[1]-19,'SYSTEMPOS')
            self.setFont('Body',8.5)
            self.drawRightString(A4[0]-42,A4[1]-19,'DEFINICIÓN FUNCIONAL · BASE 1.0')
            self.setStrokeColor(LINE)
            self.line(42,36,A4[0]-42,36)
            self.setFillColor(MUTED)
            self.setFont('Body',8)
            self.drawString(42,23,'Alcance, reglas y aceptación · 05.09.2026')
            self.drawRightString(A4[0]-42,23,f'{self._pageNumber} / {total}')
            self.restoreState()
            super().showPage()
        super().save()

def make_table(lines):
    rows=[[v.strip() for v in row.strip().strip('|').split('|')] for row in lines]
    count=len(rows[0])
    proportions=([.22,.39,.39] if count==3 else [.29,.71])
    if rows[0][0]=='Prueba': proportions=[.24,.26,.50]
    if rows[0][0]=='ID': proportions=([.11,.32,.57] if count==3 else [.15,.85])
    data=[[p(v,'th' if r==0 else 'cell') for v in row] for r,row in enumerate(rows)]
    table=Table(data,colWidths=[WIDTH*w for w in proportions],repeatRows=1,hAlign='LEFT',splitByRow=1)
    table.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(-1,0),INK),('VALIGN',(0,0),(-1,-1),'TOP'),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,PALE]),
        ('LEFTPADDING',(0,0),(-1,-1),8),('RIGHTPADDING',(0,0),(-1,-1),8),
        ('TOPPADDING',(0,0),(-1,-1),7),('BOTTOMPADDING',(0,0),(-1,-1),7),
        ('LINEBELOW',(0,0),(-1,-1),.35,LINE),
    ]))
    return table

lines=(ROOT/'docs/systempos/SystemPos.md').read_text(encoding='utf-8').splitlines()
story=[]
i=0
while i<len(lines):
    s=lines[i].strip()
    if not s:
        i+=1;continue
    if s.startswith('|'):
        rows=[]
        while i<len(lines) and lines[i].strip().startswith('|'):
            rows.append(lines[i]);i+=1
        story.extend([make_table(rows),Spacer(1,9)])
        continue
    if s.startswith('### '):story.append(p(s[4:],'h3'))
    elif s.startswith('## '):story.append(p(s[3:],'h2'))
    elif s.startswith('# '):story.append(p(s[2:],'h1'))
    elif s.startswith('Base de trabajo'):story.append(p(s,'meta'))
    else:story.append(p(s))
    i+=1

doc=SimpleDocTemplate(str(OUT/'SystemPos.review.pdf'),pagesize=A4,rightMargin=42,leftMargin=42,
                     topMargin=49,bottomMargin=49,title='SystemPos - Definición funcional',
                     author='SystemPos',subject='Visión, alcance, requisitos y aceptación del producto')
doc.build(story,canvasmaker=NumberedCanvas)
print(OUT/'SystemPos.review.pdf')
