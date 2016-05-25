<?php
/**
 * This file is part of PHPPresentation - A pure PHP library for reading and writing
 * presentations documents.
 *
 * PHPPresentation is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPPresentation/contributors.
 *
 * @link        https://github.com/PHPOffice/PHPPresentation
 * @copyright   2009-2015 PHPPresentation contributors
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */
namespace PhpOffice\PhpPresentation\Writer\PowerPoint2007;

use PhpOffice\Common\Drawing as CommonDrawing;
use PhpOffice\Common\Text;
use PhpOffice\Common\XMLWriter;
use PhpOffice\PhpPresentation\Shape\AbstractDrawing;
use PhpOffice\PhpPresentation\Shape\Chart as ShapeChart;
use PhpOffice\PhpPresentation\Shape\Comment;
use PhpOffice\PhpPresentation\Shape\Drawing\Gd as ShapeDrawingGd;
use PhpOffice\PhpPresentation\Shape\Drawing\File as ShapeDrawingFile;
use PhpOffice\PhpPresentation\Shape\Group;
use PhpOffice\PhpPresentation\Shape\Line;
use PhpOffice\PhpPresentation\Shape\Placeholder;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\RichText\BreakElement;
use PhpOffice\PhpPresentation\Shape\RichText\Run;
use PhpOffice\PhpPresentation\Shape\RichText\TextElement;
use PhpOffice\PhpPresentation\Shape\Table as ShapeTable;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Note;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Bullet;
use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Shadow;
use PhpOffice\PhpPresentation\Slide\AbstractSlide as AbstractSlideAlias;
use PhpOffice\PhpPresentation\Slide\SlideMaster;
use PhpOffice\PhpPresentation\Slide\Background;

abstract class AbstractSlide extends AbstractDecoratorWriter
{
    /**
     * @param SlideMaster $pSlideMaster
     * @param $objWriter
     * @param $relId
     * @throws \Exception
     */
    protected function writeDrawingRelations(AbstractSlideAlias $pSlideMaster, $objWriter, $relId)
    {
        if ($pSlideMaster->getShapeCollection()->count() > 0) {
            // Loop trough images and write relationships
            $iterator = $pSlideMaster->getShapeCollection()->getIterator();
            while ($iterator->valid()) {
                if ($iterator->current() instanceof ShapeDrawingFile || $iterator->current() instanceof ShapeDrawingGd) {
                    // Write relationship for image drawing
                    $this->writeRelationship(
                        $objWriter,
                        $relId,
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                        '../media/' . str_replace(' ', '_', $iterator->current()->getIndexedFilename())
                    );
                    $iterator->current()->relationId = 'rId' . $relId;
                    ++$relId;
                } elseif ($iterator->current() instanceof ShapeChart) {
                    // Write relationship for chart drawing
                    $this->writeRelationship(
                        $objWriter,
                        $relId,
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart',
                        '../charts/' . $iterator->current()->getIndexedFilename()
                    );
                    $iterator->current()->relationId = 'rId' . $relId;
                    ++$relId;
                } elseif ($iterator->current() instanceof Group) {
                    $iterator2 = $iterator->current()->getShapeCollection()->getIterator();
                    while ($iterator2->valid()) {
                        if ($iterator2->current() instanceof ShapeDrawingFile ||
                            $iterator2->current() instanceof ShapeDrawingGd
                        ) {
                            // Write relationship for image drawing
                            $this->writeRelationship(
                                $objWriter,
                                $relId,
                                'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                                '../media/' . str_replace(' ', '_', $iterator2->current()->getIndexedFilename())
                            );
                            $iterator2->current()->relationId = 'rId' . $relId;
                            ++$relId;
                        } elseif ($iterator2->current() instanceof ShapeChart) {
                            // Write relationship for chart drawing
                            $this->writeRelationship(
                                $objWriter,
                                $relId,
                                'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart',
                                '../charts/' . $iterator2->current()->getIndexedFilename()
                            );
                            $iterator2->current()->relationId = 'rId' . $relId;
                            ++$relId;
                        }
                        $iterator2->next();
                    }
                }
                $iterator->next();
            }
        }
    }

    /**
     * @param XMLWriter $objWriter
     * @param \ArrayObject|\PhpOffice\PhpPresentation\AbstractShape[] $shapes
     * @param int $shapeId
     */
    protected function writeShapeCollection(XMLWriter $objWriter, $shapes = array(), &$shapeId = 0)
    {
        foreach ($shapes as $shape) {
            // Increment $shapeId
            ++$shapeId;
            // Check type
            if ($shape instanceof RichText) {
                $this->writeShapeText($objWriter, $shape, $shapeId);
            } elseif ($shape instanceof ShapeTable) {
                $this->writeShapeTable($objWriter, $shape, $shapeId);
            } elseif ($shape instanceof Line) {
                $this->writeShapeLine($objWriter, $shape, $shapeId);
            } elseif ($shape instanceof ShapeChart) {
                $this->writeShapeChart($objWriter, $shape, $shapeId);
            } elseif ($shape instanceof AbstractDrawing) {
                $this->writeShapePic($objWriter, $shape, $shapeId);
            } elseif ($shape instanceof Group) {
                $this->writeShapeGroup($objWriter, $shape, $shapeId);
            }
        }
    }

    /**
     * Write txt
     *
     * @param  \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param  \PhpOffice\PhpPresentation\Shape\RichText $shape
     * @param  int $shapeId
     * @throws \Exception
     */
    protected function writeShapeText(XMLWriter $objWriter, RichText $shape, $shapeId)
    {
        // p:sp
        $objWriter->startElement('p:sp');
        // p:sp\p:nvSpPr
        $objWriter->startElement('p:nvSpPr');
        // p:sp\p:nvSpPr\p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', $shapeId);
        if ($shape->isPlaceholder()) {
            $objWriter->writeAttribute('name', 'Placeholder for ' . $shape->getPlaceholder()->getType());
        } else {
            $objWriter->writeAttribute('name', '');
        }
        // Hyperlink
        if ($shape->hasHyperlink()) {
            $this->writeHyperlink($objWriter, $shape);
        }
        // > p:sp\p:nvSpPr
        $objWriter->endElement();
        // p:sp\p:cNvSpPr
        $objWriter->startElement('p:cNvSpPr');
        $objWriter->writeAttribute('txBox', '1');
        $objWriter->endElement();
        // p:sp\p:cNvSpPr\p:nvPr
        if ($shape->isPlaceholder()) {
            $objWriter->startElement('p:nvPr');
            $objWriter->startElement('p:ph');
            $objWriter->writeAttribute('type', $shape->getPlaceholder()->getType());
            if (!is_null($shape->getPlaceholder()->getIdx())) {
                $objWriter->writeAttribute('idx', $shape->getPlaceholder()->getIdx());
            }
            $objWriter->endElement();
            $objWriter->endElement();
        } else {
            $objWriter->writeElement('p:nvPr', null);
        }
        // > p:sp\p:cNvSpPr
        $objWriter->endElement();
        // p:sp\p:spPr
        $objWriter->startElement('p:spPr');
        // p:sp\p:spPr\a:xfrm
        $objWriter->startElement('a:xfrm');
        $objWriter->writeAttributeIf($shape->getRotation() != 0, 'rot', CommonDrawing::degreesToAngle($shape->getRotation()));
        // p:sp\p:spPr\a:xfrm\a:off
        $objWriter->startElement('a:off');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY()));
        $objWriter->endElement();
        // p:sp\p:spPr\a:xfrm\a:ext
        $objWriter->startElement('a:ext');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($shape->getWidth()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($shape->getHeight()));
        $objWriter->endElement();
        // > p:sp\p:spPr\a:xfrm
        $objWriter->endElement();
        // p:sp\p:spPr\a:prstGeom
        $objWriter->startElement('a:prstGeom');
        $objWriter->writeAttribute('prst', 'rect');
        $objWriter->endElement();
        $this->writeFill($objWriter, $shape->getFill());
        if ($shape->getBorder()->getLineStyle() != Border::LINE_NONE) {
            $this->writeBorder($objWriter, $shape->getBorder(), '');
        }
        if ($shape->getShadow()->isVisible()) {
            $this->writeShadow($objWriter, $shape->getShadow());
        }
        // > p:sp\p:spPr
        $objWriter->endElement();
        // p:txBody
        $objWriter->startElement('p:txBody');
        // a:bodyPr
        //@link :http://msdn.microsoft.com/en-us/library/documentformat.openxml.drawing.bodyproperties%28v=office.14%29.aspx
        $objWriter->startElement('a:bodyPr');
        $verticalAlign = $shape->getActiveParagraph()->getAlignment()->getVertical();
        if ($verticalAlign != Alignment::VERTICAL_BASE && $verticalAlign != Alignment::VERTICAL_AUTO) {
            $objWriter->writeAttribute('anchor', $verticalAlign);
        }
        if ($shape->getWrap() != RichText::WRAP_SQUARE) {
            $objWriter->writeAttribute('wrap', $shape->getWrap());
        }
        $objWriter->writeAttribute('rtlCol', '0');
        if ($shape->getHorizontalOverflow() != RichText::OVERFLOW_OVERFLOW) {
            $objWriter->writeAttribute('horzOverflow', $shape->getHorizontalOverflow());
        }
        if ($shape->getVerticalOverflow() != RichText::OVERFLOW_OVERFLOW) {
            $objWriter->writeAttribute('vertOverflow', $shape->getVerticalOverflow());
        }
        if ($shape->isUpright()) {
            $objWriter->writeAttribute('upright', '1');
        }
        if ($shape->isVertical()) {
            $objWriter->writeAttribute('vert', 'vert');
        }
        $objWriter->writeAttribute('bIns', CommonDrawing::pixelsToEmu($shape->getInsetBottom()));
        $objWriter->writeAttribute('lIns', CommonDrawing::pixelsToEmu($shape->getInsetLeft()));
        $objWriter->writeAttribute('rIns', CommonDrawing::pixelsToEmu($shape->getInsetRight()));
        $objWriter->writeAttribute('tIns', CommonDrawing::pixelsToEmu($shape->getInsetTop()));
        if ($shape->getColumns() <> 1) {
            $objWriter->writeAttribute('numCol', $shape->getColumns());
        }
        // a:spAutoFit
        $objWriter->startElement('a:' . $shape->getAutoFit());
        if ($shape->getAutoFit() == RichText::AUTOFIT_NORMAL) {
            if (!is_null($shape->getFontScale())) {
                $objWriter->writeAttribute('fontScale', (int)($shape->getFontScale() * 1000));
            }
            if (!is_null($shape->getLineSpaceReduction())) {
                $objWriter->writeAttribute('lnSpcReduction', (int)($shape->getLineSpaceReduction() * 1000));
            }
        }
        $objWriter->endElement();
        $objWriter->endElement();
        // a:lstStyle
        $objWriter->writeElement('a:lstStyle', null);
        if ($shape->isPlaceholder() &&
            ($shape->getPlaceholder()->getType() == Placeholder::PH_TYPE_SLIDENUM ||
                $shape->getPlaceholder()->getType() == Placeholder::PH_TYPE_DATETIME)
        ) {
            $objWriter->startElement('a:p');
            $objWriter->startElement('a:fld');
            $objWriter->writeAttribute('id', $this->getGUID());
            $objWriter->writeAttribute('type', (
            $shape->getPlaceholder()->getType() == Placeholder::PH_TYPE_SLIDENUM ? 'slidenum' : 'datetime'));
            $objWriter->writeElement('a:t', (
            $shape->getPlaceholder()->getType() == Placeholder::PH_TYPE_SLIDENUM ? '<nr.>' : '03-04-05'));
            $objWriter->endElement();
            $objWriter->endElement();
        } else {
            // Write paragraphs
            $this->writeParagraphs($objWriter, $shape->getParagraphs());
        }
        $objWriter->endElement();
        $objWriter->endElement();
    }

    /**
     * Write table
     *
     * @param  \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param  \PhpOffice\PhpPresentation\Shape\Table $shape
     * @param  int $shapeId
     * @throws \Exception
     */
    protected function writeShapeTable(XMLWriter $objWriter, ShapeTable $shape, $shapeId)
    {
        // p:graphicFrame
        $objWriter->startElement('p:graphicFrame');
        // p:nvGraphicFramePr
        $objWriter->startElement('p:nvGraphicFramePr');
        // p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', $shapeId);
        $objWriter->writeAttribute('name', $shape->getName());
        $objWriter->writeAttribute('descr', $shape->getDescription());
        $objWriter->endElement();
        // p:cNvGraphicFramePr
        $objWriter->startElement('p:cNvGraphicFramePr');
        // a:graphicFrameLocks
        $objWriter->startElement('a:graphicFrameLocks');
        $objWriter->writeAttribute('noGrp', '1');
        $objWriter->endElement();
        $objWriter->endElement();
        // p:nvPr
        $objWriter->writeElement('p:nvPr', null);
        $objWriter->endElement();
        // p:xfrm
        $objWriter->startElement('p:xfrm');
        // a:off
        $objWriter->startElement('a:off');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY()));
        $objWriter->endElement();
        // a:ext
        $objWriter->startElement('a:ext');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($shape->getWidth()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($shape->getHeight()));
        $objWriter->endElement();
        $objWriter->endElement();
        // a:graphic
        $objWriter->startElement('a:graphic');
        // a:graphicData
        $objWriter->startElement('a:graphicData');
        $objWriter->writeAttribute('uri', 'http://schemas.openxmlformats.org/drawingml/2006/table');
        // a:tbl
        $objWriter->startElement('a:tbl');
        // a:tblPr
        $objWriter->startElement('a:tblPr');
        $objWriter->writeAttribute('firstRow', '1');
        $objWriter->writeAttribute('bandRow', '1');
        $objWriter->endElement();
        // a:tblGrid
        $objWriter->startElement('a:tblGrid');
        // Write cell widths
        $countCells = count($shape->getRow(0)->getCells());
        for ($cell = 0; $cell < $countCells; $cell++) {
            // a:gridCol
            $objWriter->startElement('a:gridCol');
            // Calculate column width
            $width = $shape->getRow(0)->getCell($cell)->getWidth();
            if ($width == 0) {
                $colCount = count($shape->getRow(0)->getCells());
                $totalWidth = $shape->getWidth();
                $width = $totalWidth / $colCount;
            }
            $objWriter->writeAttribute('w', CommonDrawing::pixelsToEmu($width));
            $objWriter->endElement();
        }
        $objWriter->endElement();
        // Colspan / rowspan containers
        $colSpan = array();
        $rowSpan = array();
        // Default border style
        $defaultBorder = new Border();
        // Write rows
        $countRows = count($shape->getRows());
        for ($row = 0; $row < $countRows; $row++) {
            // a:tr
            $objWriter->startElement('a:tr');
            $objWriter->writeAttribute('h', CommonDrawing::pixelsToEmu($shape->getRow($row)->getHeight()));
            // Write cells
            $countCells = count($shape->getRow($row)->getCells());
            for ($cell = 0; $cell < $countCells; $cell++) {
                // Current cell
                $currentCell = $shape->getRow($row)->getCell($cell);
                // Next cell right
                $nextCellRight = $shape->getRow($row)->getCell($cell + 1, true);
                // Next cell below
                $nextRowBelow = $shape->getRow($row + 1, true);
                $nextCellBelow = null;
                if ($nextRowBelow != null) {
                    $nextCellBelow = $nextRowBelow->getCell($cell, true);
                }
                // a:tc
                $objWriter->startElement('a:tc');
                // Colspan
                if ($currentCell->getColSpan() > 1) {
                    $objWriter->writeAttribute('gridSpan', $currentCell->getColSpan());
                    $colSpan[$row] = $currentCell->getColSpan() - 1;
                } elseif (isset($colSpan[$row]) && $colSpan[$row] > 0) {
                    $colSpan[$row]--;
                    $objWriter->writeAttribute('hMerge', '1');
                }
                // Rowspan
                if ($currentCell->getRowSpan() > 1) {
                    $objWriter->writeAttribute('rowSpan', $currentCell->getRowSpan());
                    $rowSpan[$cell] = $currentCell->getRowSpan() - 1;
                } elseif (isset($rowSpan[$cell]) && $rowSpan[$cell] > 0) {
                    $rowSpan[$cell]--;
                    $objWriter->writeAttribute('vMerge', '1');
                }
                // a:txBody
                $objWriter->startElement('a:txBody');
                // a:bodyPr
                $objWriter->startElement('a:bodyPr');
                $objWriter->writeAttribute('wrap', 'square');
                $objWriter->writeAttribute('rtlCol', '0');
                // a:spAutoFit
                $objWriter->writeElement('a:spAutoFit', null);
                $objWriter->endElement();
                // a:lstStyle
                $objWriter->writeElement('a:lstStyle', null);
                // Write paragraphs
                $this->writeParagraphs($objWriter, $currentCell->getParagraphs());
                $objWriter->endElement();
                // a:tcPr
                $objWriter->startElement('a:tcPr');
                // Alignment (horizontal)
                $firstParagraph = $currentCell->getParagraph(0);
                $verticalAlign = $firstParagraph->getAlignment()->getVertical();
                if ($verticalAlign != Alignment::VERTICAL_BASE && $verticalAlign != Alignment::VERTICAL_AUTO) {
                    $objWriter->writeAttribute('anchor', $verticalAlign);
                }
                // Determine borders
                $borderLeft = $currentCell->getBorders()->getLeft();
                $borderRight = $currentCell->getBorders()->getRight();
                $borderTop = $currentCell->getBorders()->getTop();
                $borderBottom = $currentCell->getBorders()->getBottom();
                $borderDiagonalDown = $currentCell->getBorders()->getDiagonalDown();
                $borderDiagonalUp = $currentCell->getBorders()->getDiagonalUp();
                // Fix PowerPoint implementation
                if (!is_null($nextCellRight)
                    && $nextCellRight->getBorders()->getRight()->getHashCode() != $defaultBorder->getHashCode()
                ) {
                    $borderRight = $nextCellRight->getBorders()->getLeft();
                }
                if (!is_null($nextCellBelow)
                    && $nextCellBelow->getBorders()->getBottom()->getHashCode() != $defaultBorder->getHashCode()
                ) {
                    $borderBottom = $nextCellBelow->getBorders()->getTop();
                }
                // Write borders
                $this->writeBorder($objWriter, $borderLeft, 'L');
                $this->writeBorder($objWriter, $borderRight, 'R');
                $this->writeBorder($objWriter, $borderTop, 'T');
                $this->writeBorder($objWriter, $borderBottom, 'B');
                $this->writeBorder($objWriter, $borderDiagonalDown, 'TlToBr');
                $this->writeBorder($objWriter, $borderDiagonalUp, 'BlToTr');
                // Fill
                $this->writeFill($objWriter, $currentCell->getFill());
                $objWriter->endElement();
                $objWriter->endElement();
            }
            $objWriter->endElement();
        }
        $objWriter->endElement();
        $objWriter->endElement();
        $objWriter->endElement();
        $objWriter->endElement();
    }

    /**
     * Write paragraphs
     *
     * @param  \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param  \PhpOffice\PhpPresentation\Shape\RichText\Paragraph[] $paragraphs
     * @throws \Exception
     */
    protected function writeParagraphs(XMLWriter $objWriter, $paragraphs)
    {
        // Loop trough paragraphs
        foreach ($paragraphs as $paragraph) {
            // a:p
            $objWriter->startElement('a:p');
            // a:pPr
            $objWriter->startElement('a:pPr');
            $objWriter->writeAttribute('algn', $paragraph->getAlignment()->getHorizontal());
            $objWriter->writeAttribute('fontAlgn', $paragraph->getAlignment()->getVertical());
            $objWriter->writeAttribute('marL', CommonDrawing::pixelsToEmu($paragraph->getAlignment()->getMarginLeft()));
            $objWriter->writeAttribute('marR', CommonDrawing::pixelsToEmu(
                $paragraph->getAlignment()->getMarginRight()
            ));
            $objWriter->writeAttribute('indent', CommonDrawing::pixelsToEmu($paragraph->getAlignment()->getIndent()));
            $objWriter->writeAttribute('lvl', $paragraph->getAlignment()->getLevel());
            // Bullet type specified?
            if ($paragraph->getBulletStyle()->getBulletType() != Bullet::TYPE_NONE) {
                // a:buFont
                $objWriter->startElement('a:buFont');
                $objWriter->writeAttribute('typeface', $paragraph->getBulletStyle()->getBulletFont());
                $objWriter->endElement();
                if ($paragraph->getBulletStyle()->getBulletType() == Bullet::TYPE_BULLET) {
                    // a:buChar
                    $objWriter->startElement('a:buChar');
                    $objWriter->writeAttribute('char', $paragraph->getBulletStyle()->getBulletChar());
                    $objWriter->endElement();
                } elseif ($paragraph->getBulletStyle()->getBulletType() == Bullet::TYPE_NUMERIC) {
                    // a:buAutoNum
                    $objWriter->startElement('a:buAutoNum');
                    $objWriter->writeAttribute('type', $paragraph->getBulletStyle()->getBulletNumericStyle());
                    if ($paragraph->getBulletStyle()->getBulletNumericStartAt() != 1) {
                        $objWriter->writeAttribute('startAt', $paragraph->getBulletStyle()->getBulletNumericStartAt());
                    }
                    $objWriter->endElement();
                }
            }
            $objWriter->endElement();
            // Loop trough rich text elements
            $elements = $paragraph->getRichTextElements();
            foreach ($elements as $element) {
                if ($element instanceof BreakElement) {
                    // a:br
                    $objWriter->writeElement('a:br', null);
                } elseif ($element instanceof Run || $element instanceof TextElement) {
                    // a:r
                    $objWriter->startElement('a:r');
                    // a:rPr
                    if ($element instanceof Run) {
                        // a:rPr
                        $objWriter->startElement('a:rPr');
                        // Lang
                        $objWriter->writeAttribute('lang', ($element->getLanguage() ?
                            $element->getLanguage() : 'en-US'));
                        // Bold
                        $objWriter->writeAttribute('b', ($element->getFont()->isBold() ? '1' : '0'));
                        // Italic
                        $objWriter->writeAttribute('i', ($element->getFont()->isItalic() ? '1' : '0'));
                        // Strikethrough
                        $objWriter->writeAttribute('strike', ($element->getFont()->isStrikethrough() ?
                            'sngStrike' : 'noStrike'));
                        // Size
                        $objWriter->writeAttribute('sz', ($element->getFont()->getSize() * 100));
                        // Underline
                        $objWriter->writeAttribute('u', $element->getFont()->getUnderline());
                        // Superscript / subscript
                        if ($element->getFont()->isSuperScript() || $element->getFont()->isSubScript()) {
                            if ($element->getFont()->isSuperScript()) {
                                $objWriter->writeAttribute('baseline', '30000');
                            } elseif ($element->getFont()->isSubScript()) {
                                $objWriter->writeAttribute('baseline', '-25000');
                            }
                        }
                        // Color - a:solidFill
                        $objWriter->startElement('a:solidFill');
                        // a:srgbClr
                        $objWriter->startElement('a:srgbClr');
                        $objWriter->writeAttribute('val', $element->getFont()->getColor()->getRGB());
                        $objWriter->endElement();
                        $objWriter->endElement();
                        // Font - a:latin
                        $objWriter->startElement('a:latin');
                        $objWriter->writeAttribute('typeface', $element->getFont()->getName());
                        $objWriter->endElement();
                        // a:hlinkClick
                        if ($element->hasHyperlink()) {
                            $this->writeHyperlink($objWriter, $element);
                        }
                        $objWriter->endElement();
                    }
                    // t
                    $objWriter->startElement('a:t');
                    $objWriter->writeCData(Text::controlCharacterPHP2OOXML($element->getText()));
                    $objWriter->endElement();
                    $objWriter->endElement();
                }
            }
            $objWriter->endElement();
        }
    }

    /**
     * Write Line Shape
     *
     * @param  \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param \PhpOffice\PhpPresentation\Shape\Line $shape
     * @param  int $shapeId
     */
    protected function writeShapeLine(XMLWriter $objWriter, Line $shape, $shapeId)
    {
        // p:sp
        $objWriter->startElement('p:cxnSp');
        // p:nvSpPr
        $objWriter->startElement('p:nvCxnSpPr');
        // p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', $shapeId);
        $objWriter->writeAttribute('name', '');
        $objWriter->endElement();
        // p:cNvCxnSpPr
        $objWriter->writeElement('p:cNvCxnSpPr', null);
        // p:nvPr
        $objWriter->writeElement('p:nvPr', null);
        $objWriter->endElement();
        // p:spPr
        $objWriter->startElement('p:spPr');
        // a:xfrm
        $objWriter->startElement('a:xfrm');
        if ($shape->getWidth() >= 0 && $shape->getHeight() >= 0) {
            // a:off
            $objWriter->startElement('a:off');
            $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX()));
            $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY()));
            $objWriter->endElement();
            // a:ext
            $objWriter->startElement('a:ext');
            $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($shape->getWidth()));
            $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($shape->getHeight()));
            $objWriter->endElement();
        } elseif ($shape->getWidth() < 0 && $shape->getHeight() < 0) {
            // a:off
            $objWriter->startElement('a:off');
            $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX() + $shape->getWidth()));
            $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY() + $shape->getHeight()));
            $objWriter->endElement();
            // a:ext
            $objWriter->startElement('a:ext');
            $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu(-$shape->getWidth()));
            $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu(-$shape->getHeight()));
            $objWriter->endElement();
        } elseif ($shape->getHeight() < 0) {
            $objWriter->writeAttribute('flipV', 1);
            // a:off
            $objWriter->startElement('a:off');
            $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX()));
            $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY() + $shape->getHeight()));
            $objWriter->endElement();
            // a:ext
            $objWriter->startElement('a:ext');
            $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($shape->getWidth()));
            $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu(-$shape->getHeight()));
            $objWriter->endElement();
        } elseif ($shape->getWidth() < 0) {
            $objWriter->writeAttribute('flipV', 1);
            // a:off
            $objWriter->startElement('a:off');
            $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX() + $shape->getWidth()));
            $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY()));
            $objWriter->endElement();
            // a:ext
            $objWriter->startElement('a:ext');
            $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu(-$shape->getWidth()));
            $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($shape->getHeight()));
            $objWriter->endElement();
        }
        $objWriter->endElement();
        // a:prstGeom
        $objWriter->startElement('a:prstGeom');
        $objWriter->writeAttribute('prst', 'line');
        $objWriter->endElement();
        if ($shape->getBorder()->getLineStyle() != Border::LINE_NONE) {
            $this->writeBorder($objWriter, $shape->getBorder(), '');
        }
        $objWriter->endElement();
        $objWriter->endElement();
    }

    /**
     * Write Shadow
     * @param XMLWriter $objWriter
     * @param Shadow $oShadow
     */
    protected function writeShadow(XMLWriter $objWriter, $oShadow)
    {
        if (!($oShadow instanceof Shadow)) {
            return;
        }

        if (!$oShadow->isVisible()) {
            return;
        }

        // a:effectLst
        $objWriter->startElement('a:effectLst');

        // a:outerShdw
        $objWriter->startElement('a:outerShdw');
        $objWriter->writeAttribute('blurRad', CommonDrawing::pixelsToEmu($oShadow->getBlurRadius()));
        $objWriter->writeAttribute('dist', CommonDrawing::pixelsToEmu($oShadow->getDistance()));
        $objWriter->writeAttribute('dir', CommonDrawing::degreesToAngle($oShadow->getDirection()));
        $objWriter->writeAttribute('algn', $oShadow->getAlignment());
        $objWriter->writeAttribute('rotWithShape', '0');

        $this->writeColor($objWriter, $oShadow->getColor(), $oShadow->getAlpha());

        $objWriter->endElement();

        $objWriter->endElement();
    }

    /**
     * Write hyperlink
     *
     * @param \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param \PhpOffice\PhpPresentation\AbstractShape|\PhpOffice\PhpPresentation\Shape\RichText\TextElement $shape
     */
    protected function writeHyperlink(XMLWriter $objWriter, $shape)
    {
        // a:hlinkClick
        $objWriter->startElement('a:hlinkClick');
        $objWriter->writeAttribute('r:id', $shape->getHyperlink()->relationId);
        $objWriter->writeAttribute('tooltip', $shape->getHyperlink()->getTooltip());
        if ($shape->getHyperlink()->isInternal()) {
            $objWriter->writeAttribute('action', $shape->getHyperlink()->getUrl());
        }
        $objWriter->endElement();
    }

    /**
     * Write Note Slide
     * @param Note $pNote
     * @throws \Exception
     * @return  string
     */
    protected function writeNote(Note $pNote)
    {
        // Create XML writer
        $objWriter = new XMLWriter(XMLWriter::STORAGE_MEMORY);
        // XML header
        $objWriter->startDocument('1.0', 'UTF-8', 'yes');
        // p:notes
        $objWriter->startElement('p:notes');
        $objWriter->writeAttribute('xmlns:a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $objWriter->writeAttribute('xmlns:p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
        $objWriter->writeAttribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        // p:cSld
        $objWriter->startElement('p:cSld');
        // p:spTree
        $objWriter->startElement('p:spTree');
        // p:nvGrpSpPr
        $objWriter->startElement('p:nvGrpSpPr');
        // p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', '1');
        $objWriter->writeAttribute('name', '');
        $objWriter->endElement();
        // p:cNvGrpSpPr
        $objWriter->writeElement('p:cNvGrpSpPr', null);
        // p:nvPr
        $objWriter->writeElement('p:nvPr', null);
        // ## p:nvGrpSpPr
        $objWriter->endElement();
        // p:grpSpPr
        $objWriter->startElement('p:grpSpPr');
        // a:xfrm
        $objWriter->startElement('a:xfrm');
        // a:off
        $objWriter->startElement('a:off');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($pNote->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($pNote->getOffsetY()));
        $objWriter->endElement(); // a:off
        // a:ext
        $objWriter->startElement('a:ext');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($pNote->getExtentX()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($pNote->getExtentY()));
        $objWriter->endElement(); // a:ext
        // a:chOff
        $objWriter->startElement('a:chOff');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($pNote->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($pNote->getOffsetY()));
        $objWriter->endElement(); // a:chOff
        // a:chExt
        $objWriter->startElement('a:chExt');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($pNote->getExtentX()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($pNote->getExtentY()));
        $objWriter->endElement(); // a:chExt
        // ## a:xfrm
        $objWriter->endElement();
        // ## p:grpSpPr
        $objWriter->endElement();
        // p:sp
        $objWriter->startElement('p:sp');
        // p:nvSpPr
        $objWriter->startElement('p:nvSpPr');
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', '1');
        $objWriter->writeAttribute('name', 'Notes Placeholder');
        $objWriter->endElement();
        // p:cNvSpPr
        $objWriter->startElement('p:cNvSpPr');
        //a:spLocks
        $objWriter->startElement('a:spLocks');
        $objWriter->writeAttribute('noGrp', '1');
        $objWriter->endElement();
        // ## p:cNvSpPr
        $objWriter->endElement();
        // p:nvPr
        $objWriter->startElement('p:nvPr');
        $objWriter->startElement('p:ph');
        $objWriter->writeAttribute('type', 'body');
        $objWriter->writeAttribute('idx', '1');
        $objWriter->endElement();
        // ## p:nvPr
        $objWriter->endElement();
        // ## p:nvSpPr
        $objWriter->endElement();
        $objWriter->writeElement('p:spPr', null);
        // p:txBody
        $objWriter->startElement('p:txBody');
        $objWriter->writeElement('a:bodyPr', null);
        $objWriter->writeElement('a:lstStyle', null);
        // Loop shapes
        $shapes = $pNote->getShapeCollection();
        foreach ($shapes as $shape) {
            // Check type
            if ($shape instanceof RichText) {
                $paragraphs = $shape->getParagraphs();
                $this->writeParagraphs($objWriter, $paragraphs);
            }
        }
        // ## p:txBody
        $objWriter->endElement();
        // ## p:sp
        $objWriter->endElement();
        // ## p:spTree
        $objWriter->endElement();
        // ## p:cSld
        $objWriter->endElement();
        // ## p:notes
        $objWriter->endElement();
        // Return
        return $objWriter->getData();
    }

    /**
     * Write chart
     *
     * @param \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param \PhpOffice\PhpPresentation\Shape\Chart $shape
     * @param  int $shapeId
     */
    protected function writeShapeChart(XMLWriter $objWriter, ShapeChart $shape, $shapeId)
    {
        // p:graphicFrame
        $objWriter->startElement('p:graphicFrame');
        // p:nvGraphicFramePr
        $objWriter->startElement('p:nvGraphicFramePr');
        // p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', $shapeId);
        $objWriter->writeAttribute('name', $shape->getName());
        $objWriter->writeAttribute('descr', $shape->getDescription());
        $objWriter->endElement();
        // p:cNvGraphicFramePr
        $objWriter->writeElement('p:cNvGraphicFramePr', null);
        // p:nvPr
        $objWriter->writeElement('p:nvPr', null);
        $objWriter->endElement();
        // p:xfrm
        $objWriter->startElement('p:xfrm');
        $objWriter->writeAttributeIf($shape->getRotation() != 0, 'rot', CommonDrawing::degreesToAngle($shape->getRotation()));
        // a:off
        $objWriter->startElement('a:off');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY()));
        $objWriter->endElement();
        // a:ext
        $objWriter->startElement('a:ext');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($shape->getWidth()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($shape->getHeight()));
        $objWriter->endElement();
        $objWriter->endElement();
        // a:graphic
        $objWriter->startElement('a:graphic');
        // a:graphicData
        $objWriter->startElement('a:graphicData');
        $objWriter->writeAttribute('uri', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
        // c:chart
        $objWriter->startElement('c:chart');
        $objWriter->writeAttribute('xmlns:c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
        $objWriter->writeAttribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $objWriter->writeAttribute('r:id', $shape->relationId);
        $objWriter->endElement();
        $objWriter->endElement();
        $objWriter->endElement();
        $objWriter->endElement();
    }

    /**
     * Write pic
     *
     * @param  \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param  \PhpOffice\PhpPresentation\Shape\AbstractDrawing $shape
     * @param  int $shapeId
     * @throws \Exception
     */
    protected function writeShapePic(XMLWriter $objWriter, AbstractDrawing $shape, $shapeId)
    {
        // p:pic
        $objWriter->startElement('p:pic');
        // p:nvPicPr
        $objWriter->startElement('p:nvPicPr');
        // p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('id', $shapeId);
        $objWriter->writeAttribute('name', $shape->getName());
        $objWriter->writeAttribute('descr', $shape->getDescription());
        // a:hlinkClick
        if ($shape->hasHyperlink()) {
            $this->writeHyperlink($objWriter, $shape);
        }
        $objWriter->endElement();
        // p:cNvPicPr
        $objWriter->startElement('p:cNvPicPr');
        // a:picLocks
        $objWriter->startElement('a:picLocks');
        $objWriter->writeAttribute('noChangeAspect', '1');
        $objWriter->endElement();
        $objWriter->endElement();
        // p:nvPr
        $objWriter->writeElement('p:nvPr', null);
        $objWriter->endElement();
        // p:blipFill
        $objWriter->startElement('p:blipFill');
        // a:blip
        $objWriter->startElement('a:blip');
        $objWriter->writeAttribute('r:embed', $shape->relationId);
        $objWriter->endElement();
        // a:stretch
        $objWriter->startElement('a:stretch');
        $objWriter->writeElement('a:fillRect', null);
        $objWriter->endElement();
        $objWriter->endElement();
        // p:spPr
        $objWriter->startElement('p:spPr');
        // a:xfrm
        $objWriter->startElement('a:xfrm');
        $objWriter->writeAttributeIf($shape->getRotation() != 0, 'rot', CommonDrawing::degreesToAngle($shape->getRotation()));
        // a:off
        $objWriter->startElement('a:off');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($shape->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($shape->getOffsetY()));
        $objWriter->endElement();
        // a:ext
        $objWriter->startElement('a:ext');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($shape->getWidth()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($shape->getHeight()));
        $objWriter->endElement();
        $objWriter->endElement();
        // a:prstGeom
        $objWriter->startElement('a:prstGeom');
        $objWriter->writeAttribute('prst', 'rect');
        // a:avLst
        $objWriter->writeElement('a:avLst', null);
        $objWriter->endElement();
        if ($shape->getBorder()->getLineStyle() != Border::LINE_NONE) {
            $this->writeBorder($objWriter, $shape->getBorder(), '');
        }
        if ($shape->getShadow()->isVisible()) {
            $this->writeShadow($objWriter, $shape->getShadow());
        }
        $objWriter->endElement();
        $objWriter->endElement();
    }

    /**
     * Write group
     *
     * @param \PhpOffice\Common\XMLWriter $objWriter XML Writer
     * @param \PhpOffice\PhpPresentation\Shape\Group $group
     * @param  int $shapeId
     */
    protected function writeShapeGroup(XMLWriter $objWriter, Group $group, &$shapeId)
    {
        // p:grpSp
        $objWriter->startElement('p:grpSp');
        // p:nvGrpSpPr
        $objWriter->startElement('p:nvGrpSpPr');
        // p:cNvPr
        $objWriter->startElement('p:cNvPr');
        $objWriter->writeAttribute('name', 'Group ' . $shapeId++);
        $objWriter->writeAttribute('id', $shapeId);
        $objWriter->endElement(); // p:cNvPr
        // NOTE: Re: $shapeId This seems to be how PowerPoint 2010 does business.
        // p:cNvGrpSpPr
        $objWriter->writeElement('p:cNvGrpSpPr', null);
        // p:nvPr
        $objWriter->writeElement('p:nvPr', null);
        $objWriter->endElement(); // p:nvGrpSpPr
        // p:grpSpPr
        $objWriter->startElement('p:grpSpPr');
        // a:xfrm
        $objWriter->startElement('a:xfrm');
        // a:off
        $objWriter->startElement('a:off');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($group->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($group->getOffsetY()));
        $objWriter->endElement(); // a:off
        // a:ext
        $objWriter->startElement('a:ext');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($group->getExtentX()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($group->getExtentY()));
        $objWriter->endElement(); // a:ext
        // a:chOff
        $objWriter->startElement('a:chOff');
        $objWriter->writeAttribute('x', CommonDrawing::pixelsToEmu($group->getOffsetX()));
        $objWriter->writeAttribute('y', CommonDrawing::pixelsToEmu($group->getOffsetY()));
        $objWriter->endElement(); // a:chOff
        // a:chExt
        $objWriter->startElement('a:chExt');
        $objWriter->writeAttribute('cx', CommonDrawing::pixelsToEmu($group->getExtentX()));
        $objWriter->writeAttribute('cy', CommonDrawing::pixelsToEmu($group->getExtentY()));
        $objWriter->endElement(); // a:chExt
        $objWriter->endElement(); // a:xfrm
        $objWriter->endElement(); // p:grpSpPr

        $this->writeShapeCollection($objWriter, $group->getShapeCollection(), $shapeId);

        $objWriter->endElement(); // p:grpSp
    }

    /**
     * @param \PhpOffice\PhpPresentation\Slide\AbstractSlide $pSlide
     * @param $objWriter
     */
    protected function writeSlideBackground(AbstractSlideAlias $pSlide, XMLWriter $objWriter)
    {
        if (!($pSlide->getBackground() instanceof Slide\AbstractBackground)) {
            return;
        }
        $oBackground = $pSlide->getBackground();
        // p:bg
        $objWriter->startElement('p:bg');
        if ($oBackground instanceof Slide\Background\Color) {
            // p:bgPr
            $objWriter->startElement('p:bgPr');
            // a:solidFill
            $objWriter->startElement('a:solidFill');
            // a:srgbClr
            $objWriter->startElement('a:srgbClr');
            $objWriter->writeAttribute('val', $oBackground->getColor()->getRGB());
            $objWriter->endElement();
            // > a:solidFill
            $objWriter->endElement();
            // > p:bgPr
            $objWriter->endElement();
        }
        if ($oBackground instanceof Slide\Background\Image) {
            // p:bgPr
            $objWriter->startElement('p:bgPr');
            // a:blipFill
            $objWriter->startElement('a:blipFill');
            // a:blip
            $objWriter->startElement('a:blip');
            $objWriter->writeAttribute('r:embed', $oBackground->relationId);
            // > a:blipFill
            $objWriter->endElement();
            // a:stretch
            $objWriter->startElement('a:stretch');
            // a:fillRect
            $objWriter->writeElement('a:fillRect');
            // > a:stretch
            $objWriter->endElement();
            // > a:blipFill
            $objWriter->endElement();
            // > p:bgPr
            $objWriter->endElement();
        }
        /**
         * @link : http://www.officeopenxml.com/prSlide-background.php
         */
        if ($oBackground instanceof Slide\Background\SchemeColor) {
            // p:bgRef
            $objWriter->startElement('p:bgRef');
            $objWriter->writeAttribute('idx', '1001');
            // a:schemeClr
            $objWriter->startElement('a:schemeClr');
            $objWriter->writeAttribute('val', $oBackground->getSchemeColor()->getValue());
            $objWriter->endElement();
            // > p:bgRef
            $objWriter->endElement();
        }
        // > p:bg
        $objWriter->endElement();
    }

    private function getGUID()
    {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                . substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12)
                . chr(125);// "}"
            return $uuid;
        }
    }
}
