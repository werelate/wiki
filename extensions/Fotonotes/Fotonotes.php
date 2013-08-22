<?php
// Copyright 2006 by Dallan Quass
// Released under the GPL.
require_once("$IP/includes/Image.php");

class Fotonotes {
   private $img;

   /**
     * Construct a new Fotonotes object
     */
	public function __construct($title) {
		$this->img = new Image($title);
	}

// Extracted from ImagePage.openShowImage
	private function getShowImageParameters() {
		global $wgUser, $wgImageLimits, $wgUseImageResize, $wgGenerateThumbnailOnParse;

		$url = ''; $width = 0; $height = 0;

		if( $wgUser->getOption( 'imagesize' ) == '' ) {
			$sizeSel = User::getDefaultOption( 'imagesize' );
		} else {
			$sizeSel = intval( $wgUser->getOption( 'imagesize' ) );
		}
		if( !isset( $wgImageLimits[$sizeSel] ) ) {
			$sizeSel = User::getDefaultOption( 'imagesize' );
		}
		$max = $wgImageLimits[$sizeSel];
		$maxWidth = $max[0];
		$maxHeight = $max[1];

		if ( $this->img->exists() ) {
			# image
			$width = $this->img->getWidth();
			$height = $this->img->getHeight();

			if ( $this->img->allowInlineDisplay() and $width and $height) {
				# image

				# We'll show a thumbnail of this image
				if ( $width > $maxWidth || $height > $maxHeight ) {
					# Calculate the thumbnail size.
					# First case, the limiting factor is the width, not the height.
					if ( $width / $height >= $maxWidth / $maxHeight ) {
						$height = round( $height * $maxWidth / $width);
						$width = $maxWidth;
						# Note that $height <= $maxHeight now.
					} else {
						$newwidth = floor( $width * $maxHeight / $height);
						$height = round( $height * $newwidth / $width );
						$width = $newwidth;
						# Note that $height <= $maxHeight now, but might not be identical
						# because of rounding.
					}

					if( $wgUseImageResize ) {
						$thumbnail = $this->img->getThumbnail( $width, -1, $wgGenerateThumbnailOnParse );
						if ( $thumbnail == null ) {
							$url = $this->img->getViewURL();
						} else {
							$url = $thumbnail->getURL();
						}
					} else {
						# No resize ability? Show the full image, but scale
						# it down in the browser so it fits on the page.
						$url = $this->img->getViewURL();
					}
				} else {
					$url = $this->img->getViewURL();
				}
			}
		}
		return array($url, $width, $height);
	}

	/**
	 * Render the DIV containing the image notes
	 * @param $notes pass in a string or a simplexml object
	 */
   public function renderImageNotes($notes, $divID = 'fn-default-notes') {
      $result = '';
      if ($this->img && $this->img->exists()) {
         $height = $this->img->getHeight();
         $width = $this->img->getWidth();
         if ($height && $width) {
      		$result .= "<div id=\"$divID\" style=\"display:none\" title=\"$height:$width\">\n";
      		if (is_string($notes)) {
         		$pattern = "#<note\s+left=\"(\d+)\"\s+top=\"(\d+)\"\s+right=\"(\d+)\"\s+bottom=\"(\d+)\"\s+title=\"([^\"]*)\">([^<]*)</note>#i";
         		$matches = array();
               preg_match_all($pattern, $notes, $matches, PREG_SET_ORDER);
               foreach ($matches as $match) {
                  $title = htmlspecialchars($match[5]);
                  $content = htmlspecialchars($match[6]);
                  $result .= "<span style=\"left:{$match[1]}px; top:{$match[2]}px; right:{$match[3]}px; bottom:{$match[4]}px;\" title=\"$title\">$content</span>\n";
               }
      		}
      		else if (isset($notes)) {
      		   foreach ($notes as $note) {
                  $title = htmlspecialchars((string)$note['title']);
                  $content = htmlspecialchars((string)$note);
      		      $result .= "<span style=\"left:{$note['left']}px; top:{$note['top']}px; right:{$note['right']}px; bottom:{$note['bottom']}px;\" title=\"$title\">$content</span>\n";
      		   }
      		}

      		$result .= "</div>";
         }
      }
      return $result;
   }

	/**
	 * Render an editable IMG and the DIV containing the image notes
	 * @param $notes pass in a string or a simplexml object
	 */
   public function renderEditableImage($notes) {
      $result = '';
      if ($this->img && $this->img->exists()) {
         list($url, $width, $height) = $this->getShowImageParameters();
         if ($height && $width) {
   		   $result .= "<img id=\"fn-editable\" class=\"fn-image fn-editable\" border=\"0\" src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" />"
      		   .$this->renderImageNotes($notes, 'fn-editable-notes');
         }
      }
      return $result;
   }
}
?>
