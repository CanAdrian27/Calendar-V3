<?php
ini_set('error_reporting', false);
	use Mexitek\PHPColors\Color;
function calColorSet($color,$alpha)
{
	$color  = new Color($color);
	$string = '';

	if( $color->isLight())
	{
		for($i = 1; $i <10;$i++)
		{
			$color  = new Color($color->darken());
			$cc = getContrastColor($color->getRgb());
			$string .= "
						--ae-cal$i-color: #". $color->getHex().";
						--ae-com$i-color: ". $cc.";
						";
								
			if($i ==3)
			{
				$colourKey = $color->getRgb();
				$string .=  '
				--fc-today-bg-color: rgba('.$colourKey['R'].','.$colourKey['G'].','.$colourKey['B'].','.$alpha.');
				--fc-today-border: 3px solid #000;
				--fc-event-border-color:rgba('.$colourKey['R'].','.$colourKey['G'].','.$colourKey['B'].','.$alpha.');
				--ae-holiday-color:rgba('.$colourKey['R'].','.$colourKey['G'].','.$colourKey['B'].','.($alpha*.5).');
				';
			}
		}
	}else 
	{
		for($i = 0; $i <10;$i++)
		{
			$color  = new Color($color->lighten());
			$cc = getContrastColor($color->getRgb());
			$string .=  "
				--ae-cal$i-color: #". $color->getHex().";
				--ae-com$i-color: ". $cc.";
			 ";
			
			if($i ==3)
			{
				$colourKey = $color->getRgb();
				$string .=  '
				--fc-today-bg-color: rgba('.$colourKey['R'].','.$colourKey['G'].','.$colourKey['B'].','.$alpha.');
				--fc-today-border: 3px solid #fff;
				--fc-event-border-color:rgba('.$colourKey['R'].','.$colourKey['G'].','.$colourKey['B'].','.$alpha.');
				--ae-holiday-color:rgba('.$colourKey['R'].','.$colourKey['G'].','.$colourKey['B'].','.($alpha*.5).');
				';
			}
		}
	}
	
	
	return $string;	

}


function makeCalBackground($dir,$support_dir,$imagecnt)
{
	$allFiles = array_values(array_filter(scandir($dir), fn($f) => $f[0] !== '.'));
	$files = array_values(array_filter($allFiles, function($f) use ($support_dir) {
		$stem = explode('.', $f)[0];
		return file_exists($support_dir . $stem . '_1.jpg');
	}));
	if (count($files) === 0) $files = $allFiles; // fall back if none are processed yet
	$filename = $files[rand(0, count($files) - 1)];
	$fileparts = explode('.', $filename);
	$retArr['image'] = $dir.$filename;
	$retArr['background'] = 'url("'.$support_dir.$fileparts[0].'_1.jpg");';
	if(file_exists($support_dir.$fileparts[0].'_small.jpg'))
	{
		$imagick = new \Imagick(realpath($support_dir.$fileparts[0].'_small.jpg'));
		$pixel = $imagick->getImagePixelColor(1, 1); 
		$colors = $pixel->getColor();
		$retArr['color'] =  $colors;
		$colors = $pixel->getColorAsString();
		$retArr['colorStr'] =  $colors;
	}else
	{
		$retArr['color'] = ['r'=>255,'g'=>255,'b'=>255,'a'=>1];
	}
	return $retArr;
}

function setColours($images,$alpha)
{
	$colourKey = $images['color'];
	$color = '#'.str_pad(dechex($images['color']['r']),2,"0",STR_PAD_LEFT).str_pad(dechex($images['color']['g']),2,"0",STR_PAD_LEFT).str_pad(dechex($images['color']['b']),2,"0",STR_PAD_LEFT);
	$colorset = calColorSet($color,$alpha);
	$cc = getContrastColor($images['color']);
	if($cc =='#FFFFFF')
	{
		return $colorset.'
			
			--ae-generic-background: rgba(0,0,0,1);
			--ae-generic-color: rgba(255,255,255,1);
			--ae-generic-border: rgba(255,255,255,1);
			--ae-blur-overlay: rgba(0,0,0,.3);
		';
	}else
	{
		return $colorset.'
			
			--ae-generic-background: rgba(255,255,255,1);
			--ae-generic-color: rgba(0,0,0,1);
			--ae-generic-border: rgba(0,0,0,1);
			--ae-blur-overlay: rgba(255,255,255,.3);
		';
	}

}


function getContrastColor($color)
{
		// hexColor RGB
		if(isset($color['r']))
		{
			$R1 = $color['r'];
			$G1 = $color['g'];
			$B1 = $color['b'];
		}
		if(isset($color['R']))
		{
			$R1 = $color['R'];
			$G1 = $color['G'];
			$B1 = $color['B'];
		}
		// Black RGB
		$blackColor = "#000000";
		$R2BlackColor = hexdec(substr($blackColor, 1, 2));
		$G2BlackColor = hexdec(substr($blackColor, 3, 2));
		$B2BlackColor = hexdec(substr($blackColor, 5, 2));

		 // Calc contrast ratio
		 $L1 = 0.2126 * pow($R1 / 255, 2.2) +
			   0.7152 * pow($G1 / 255, 2.2) +
			   0.0722 * pow($B1 / 255, 2.2);

		$L2 = 0.2126 * pow($R2BlackColor / 255, 2.2) +
			  0.7152 * pow($G2BlackColor / 255, 2.2) +
			  0.0722 * pow($B2BlackColor / 255, 2.2);

		$contrastRatio = 0;
		if ($L1 > $L2) {
			$contrastRatio = (int)(($L1 + 0.05) / ($L2 + 0.05));
		} else {
			$contrastRatio = (int)(($L2 + 0.05) / ($L1 + 0.05));
		}
	
		// If contrast is more than 5, return black color
		if ($contrastRatio > 5) {
			return '#000000';
		} else { 
			// if not, return white color.
			return '#FFFFFF';
		}
}

?>