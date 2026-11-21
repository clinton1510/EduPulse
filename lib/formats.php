<?php
// Small dependency-free XLSX transport. ZIP contents stay in memory; never extracted to disk.
function zipStore(array $files): string {
 $data='';$central='';$count=0;
 foreach($files as $name=>$content){$offset=strlen($data);$crc=crc32($content);$size=strlen($content);$length=strlen($name);$data.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$size,$size,$length,0).$name.$content;$central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;$count++;}
 return $data.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($data),0);
}
function xmlEscape($value): string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_XML1,'UTF-8');}
function xlsxWrite(array $rows): string {
 $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
 foreach($rows as $i=>$row){$sheet.='<row r="'.($i+1).'">';foreach($row as $value)$sheet.='<c t="inlineStr"><is><t xml:space="preserve">'.xmlEscape($value).'</t></is></c>';$sheet.='</row>';}$sheet.='</sheetData></worksheet>';
 return zipStore(['[Content_Types].xml'=>'<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>','_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>','xl/workbook.xml'=>'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="EduPulse Report" sheetId="1" r:id="rId1"/></sheets></workbook>','xl/_rels/workbook.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>','xl/worksheets/sheet1.xml'=>$sheet]);
}
function zipRead(string $bytes): array {
 $end=strrpos($bytes,"PK\x05\x06");if($end===false||strlen($bytes)<$end+22)throw new RuntimeException('Invalid XLSX ZIP directory.');
 $e=unpack('vdisk/vstart/vcountDisk/vcount/Vsize/Voffset/vcomment',substr($bytes,$end+4,18));if($e['count']>100||$e['disk']!==0||$e['start']!==0)throw new RuntimeException('Unsupported or oversized workbook.');
 $offset=$e['offset'];$files=[];$total=0;
 for($i=0;$i<$e['count'];$i++){
  if(substr($bytes,$offset,4)!=="PK\x01\x02")throw new RuntimeException('Invalid workbook directory.');
  $h=unpack('vmade/vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Vlocal',substr($bytes,$offset+4,42));
  $name=substr($bytes,$offset+46,$h['name']);$offset+=46+$h['name']+$h['extra']+$h['comment'];$total+=$h['size'];
  if($h['flags']&1||$h['size']>8000000||$total>16000000||!in_array($h['method'],[0,8],true))throw new RuntimeException('Encrypted, unsupported or oversized workbook.');
  if(!in_array($name,['xl/worksheets/sheet1.xml','xl/sharedStrings.xml'],true))continue;
  $local=$h['local'];if(substr($bytes,$local,4)!=="PK\x03\x04")throw new RuntimeException('Invalid workbook entry.');$lh=unpack('vname/vextra',substr($bytes,$local+26,4));$start=$local+30+$lh['name']+$lh['extra'];$raw=substr($bytes,$start,$h['compressed']);$content=$h['method']===8?@gzinflate($raw,8000000):$raw;
  if($content===false||strlen($content)!==$h['size']||sprintf('%u',crc32($content))!==sprintf('%u',$h['crc']))throw new RuntimeException('Corrupt workbook entry.');$files[$name]=$content;
 }
 return $files;
}
function safeXml(string $xml): SimpleXMLElement {
 if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false)throw new RuntimeException('XML declarations with entities are not permitted.');
 $previous=libxml_use_internal_errors(true);$parsed=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);if(!$parsed)throw new RuntimeException('Invalid workbook XML.');return $parsed;
}
function xlsxRead(string $bytes): array {
 $files=zipRead($bytes);if(!isset($files['xl/worksheets/sheet1.xml']))throw new RuntimeException('The first worksheet is missing.');$strings=[];
 if(isset($files['xl/sharedStrings.xml']))foreach(safeXml($files['xl/sharedStrings.xml'])->xpath('//*[local-name()="si"]') as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $part)$text.=(string)$part;$strings[]=$text;}
 $rows=[];foreach(safeXml($files['xl/worksheets/sheet1.xml'])->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){$values=[];$index=0;foreach($row->xpath('./*[local-name()="c"]') as $cell){if($cell->xpath('./*[local-name()="f"]'))throw new RuntimeException('Formulas are not permitted in imported datasets.');$ref=(string)$cell['r'];if(preg_match('/^([A-Z]+)[0-9]+$/',$ref,$match)){$index=0;foreach(str_split($match[1]) as $char)$index=$index*26+ord($char)-64;$index--;}$type=(string)$cell['t'];$text='';if($type==='inlineStr'){foreach($cell->xpath('.//*[local-name()="t"]') as $part)$text.=(string)$part;}else{$v=$cell->xpath('./*[local-name()="v"]');$text=isset($v[0])?(string)$v[0]:'';if($type==='s')$text=$strings[(int)$text]??'';}$values[$index++]=$text;if($index>30)throw new RuntimeException('Too many columns.');}if($values){$dense=array_fill(0,max(array_keys($values))+1,'');foreach($values as $k=>$v)$dense[$k]=$v;$rows[]=$dense;}if(count($rows)>2001)throw new RuntimeException('Maximum 2,000 data rows per import.');}
 return $rows;
}
function pdfWrite(array $rows): string {
 $lines=[];foreach($rows as $row){$line=implode(' | ',array_map(fn($v)=>str_replace(["\r","\n"],' ',(string)$v),$row));$line=iconv('UTF-8','Windows-1252//TRANSLIT',$line);foreach(explode("\n",wordwrap($line,118,"\n",true)) as $part)$lines[]=$part;}
 $chunks=array_chunk($lines,48);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];$kids=[];
 foreach($chunks as $i=>$chunk){$page=4+$i*2;$stream=$page+1;$kids[]="$page 0 R";$content="BT /F1 9 Tf 12 TL 36 560 Td\n";foreach($chunk as $line){$escaped=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$content.="($escaped) Tj T*\n";}$content.='ET';$objects[$page]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $stream 0 R >>";$objects[$stream]='<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";}
 $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$pdf.='xref'."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($objects as $id=>$unused)$pdf.=sprintf("%010d 00000 n \n",$offsets[$id]);$pdf.='trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;
}
