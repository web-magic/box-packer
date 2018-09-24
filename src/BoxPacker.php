<?php


namespace WebMagic\BoxPacker;


class BoxPacker
{
    /** @var array Collection of images */
    protected $unpackedBoxes = [];

    /** @var array Packed images collection */
    protected $packedBoxes = [];

    /** @var null Needed width */
    protected $containerWidth = null;

    /** @var array Sprite map */
    protected $map;

    /** @var int Space between images */
    protected $spaceBetween;

    /**
     * BoxPacker constructor.
     *
     * @param null $containerWidth
     * @param int  $spaceBetween
     */
    public function __construct($containerWidth, $spaceBetween = 0)
    {
        $this->containerWidth = $containerWidth;
        $this->spaceBetween = $spaceBetween;
    }


    /**
     * Add image to collection
     *
     * @param $key
     * @param $width
     * @param $height
     */
    public function addBox($key, $width, $height)
    {
        $this->unpackedBoxes[] = [
            'key' => $key,
            'width' => $width,
            'height' => $height,
            'square' => $width * $height,
            'x' => null,
            'y' => null,
            'biggestSize' => null
        ];
    }

    /**
     * Return current container width
     *
     * @return null
     */
    public function getContainerWidth()
    {
        return $this->containerWidth;
    }

    /**
     * Set container width
     *
     * @param null $containerWidth
     */
    public function setContainerWidth($containerWidth)
    {
        $this->containerWidth = $containerWidth;
    }

    /**
     * Build sprite
     *
     * @return array
     * @throws \Exception
     */
    public function pack()
    {
        $this->arrangeBoxes();

        return $this->packedBoxes;
    }

    /**
     * @param int $height
     *
     * @return array
     * @throws \Exception
     */
    public function packWithGivenHeightAndResize($height = 0)
    {
        $defaultResult = $this->defaultPacking($height);
        if (!$defaultResult) {
            return $defaultResult;
        }

        //Need for resize
        $reduceStep = 1;

        while ($this->getHeight() > $height) {
            $this->reset();
            $this->reduceWidthAndHeight($reduceStep);
            $this->pack();
        }

        return $this->packedBoxes;
    }

    /**
     * @param int $height
     *
     * @return array|bool
     * @throws \Exception
     */
    public function packWithGivenHeightAndNormalizedReduction($height = 0)
    {
//        $defaultResult = $this->defaultPacking($height);
//        if ($defaultResult) {
//            return $defaultResult;
//        }
//
//        $this->reset();
        $this->normalizeBoxesSizes();
        $this->pack();

        //Need for resize
        $reduceStep = 1;
        $givenHeight = $this->getHeight();

        while ($givenHeight > $height) {
            $this->reset();
            $this->reduceWidthAndHeightOfBiggestBox($reduceStep);
            $this->pack();

            $givenHeight = $this->getHeight();
        }

        while ($givenHeight < $height) {
            $this->reset();
            $status = $this->increaseWidthAndHeightOfSmallestBox($reduceStep);
            $this->pack();

            $givenHeight = $this->getHeight();

            if ($status === false) {
                break;
            }
        }

        return $this->packedBoxes;
    }

    /**
     * Reduce all boxes with given rate
     *
     * @param int $step
     */
    protected function reduceWidthAndHeight($step = 1)
    {
        foreach ($this->unpackedBoxes as &$box) {
            $box['width'] = $box['width'] - $step;
            $box['height'] = $box['height'] - $step;
        }
    }

    /**
     * Reduce width and height of biggest box
     *
     * @param int $step
     *
     * @throws \Exception
     */
    protected function reduceWidthAndHeightOfBiggestBox($step = 1)
    {
        $max = 0;
        $biggestKey = 0;
        foreach ($this->unpackedBoxes as $key => $box) {
            if ($max < $box['square']) {
                $max = $box['square'];
                $biggestKey = $key;
            }
        }

        $this->unpackedBoxes[$biggestKey]['width'] = $this->unpackedBoxes[$biggestKey]['width'] - $step;
        $this->unpackedBoxes[$biggestKey]['height'] = $this->unpackedBoxes[$biggestKey]['height'] - $step;
        $this->unpackedBoxes[$biggestKey]['square'] = $this->unpackedBoxes[$biggestKey]['width'] * $this->unpackedBoxes[$biggestKey]['height'];

        if ($this->unpackedBoxes[$biggestKey]['square'] <= 0) {
            throw new \Exception('Current boxes can not be resized with current height');
        }
    }

    /**
     * Reduce width and height of smallest box
     *
     * @param int $step
     *
     * @return bool
     */
    protected function increaseWidthAndHeightOfSmallestBox($step = 1)
    {
        $min = $this->unpackedBoxes[0]['square'];
        $smallestKey = 0;
        foreach ($this->unpackedBoxes as $key => $box) {
            if ($min > $box['square']) {
                $min = $box['square'];
                $smallestKey = $key;
            }
        }

        $newWidth = $this->unpackedBoxes[$smallestKey]['width'] + $step;

        if ($newWidth >= $this->containerWidth) {
            return false;
        }

        $this->unpackedBoxes[$smallestKey]['width'] = $newWidth;
        $this->unpackedBoxes[$smallestKey]['height'] = $this->unpackedBoxes[$smallestKey]['height'] + $step;
        $this->unpackedBoxes[$smallestKey]['square'] = $newWidth * $this->unpackedBoxes[$smallestKey]['height'];
    }

    /**
     * Normalize box sized based on minimal square
     */
    protected function normalizeBoxesSizes()
    {
        $min = $this->unpackedBoxes[0];
        foreach ($this->unpackedBoxes as $key => $box) {
            if ($min > $box['square']) {
                $min = $box['square'];
            }
        }

        $step = 1;
        foreach ($this->unpackedBoxes as $key => &$box) {
            while ($box['square'] > $min) {
                $box['width'] = $box['width'] - $step;
                $box['height'] = $box['height'] - $step;
                $box['square'] = $box['width'] * $box['height'];
            }
        }
    }

    /**
     * Default packing with set height
     *
     * @param $height
     *
     * @return array|bool
     * @throws \Exception
     */
    protected function defaultPacking($height)
    {
        if ($height == 0) {
            return $this->pack();
        }

        //Try pack without resizing
        $this->pack();
        $givenHeight = $this->getHeight();
        if ($givenHeight == $height) {
            return $this->packedBoxes;
        }

        return false;
    }

    /**
     * Set packed boxes as unpacked
     */
    protected function reset()
    {
        $this->unpackedBoxes = $this->packedBoxes;
        $this->packedBoxes = [];

        unset($this->map);
    }

    /**
     * Calculate container height
     *
     * @return mixed
     * @throws \Exception
     */
    public function getHeight()
    {
        //Check for unpacked boxes and call packing if they exists
        if (count($this->unpackedBoxes)) {
            $this->pack();
        }

        $map = $this->getMap();
        return max($map);
    }

    /**
     * Pack images to container
     * @throws \Exception
     */
    protected function arrangeBoxes()
    {
        //Sort from bigger width to smaller
        $this->sortBoxes();

        if ($this->unpackedBoxes[0]['width'] > $this->containerWidth) {
            $boxWidth = $this->unpackedBoxes[0]['width'];
            $boxKey = $this->unpackedBoxes[0]['key'];
            throw new \Exception("One of boxes bigger than container: width: $boxWidth px, key: $boxKey");
        }

        while (count($this->unpackedBoxes)) {

            $freePositionData = $this->getFreePositionData();
            $x = $freePositionData['lowestPosition'];
            $y = $freePositionData['lowest'];
            $width = $freePositionData['availableWidth'];
            $bestFitImageIndex = $this->getBestFitBox($width, $x);

            if (is_null($bestFitImageIndex)) {
                $this->markCurrentFreeRowAsUnavailable($x, $width);
                continue;
            }

            $this->packBox($bestFitImageIndex, $x, $y);
        }
    }

    /**
     * Sort images by width
     */
    protected function sortBoxes()
    {
        $boxes = $this->unpackedBoxes;

        //Sort from bigger width to smaller
        usort($boxes, function ($imageData1, $imageData2) {
            return $imageData2['width'] - $imageData1['width'];
        });

        $this->unpackedBoxes = $boxes;
    }

    /**
     * Pack image
     *
     * @param $index
     * @param $x
     * @param $y
     */
    protected function packBox($index, $x, $y)
    {
        $imageData = $this->unpackedBoxes[$index];

        $this->updateMap($x, $y, $imageData['width'], $imageData['height']);
        $this->markBoxPacked($index, $x, $y);
    }

    /**
     * Update map with images params
     *
     * @param $x
     * @param $width
     * @param $height
     */
    protected function updateMap($x, $y, $width, $height)
    {
        $map = $this->getMap();
        $firstPoint = $x;
        $lastPoint = $x + $width;
        //Allowance for indentation for not first element
        $lastPoint = $firstPoint !== 0 ? $lastPoint + $this->spaceBetween : $lastPoint;

        for (; $x < $lastPoint; $x++) {
            $map[$x] += $y !== 0 ? $height + $this->spaceBetween : $height;
        }

        $this->map = $map;
    }

    /**
     * Mark image as packed
     *
     * @param $boxDataIndex
     * @param $x
     * @param $y
     */
    protected function markBoxPacked($boxDataIndex, $x, $y)
    {
        $box = $this->unpackedBoxes[$boxDataIndex];
        $box['x'] = $x !== 0 ? $x + $this->spaceBetween : 0;
        $box['y'] = $y !== 0 ? $y + $this->spaceBetween : 0;

        $this->packedBoxes[] = $box;
        $unpackedBoxes = $this->unpackedBoxes;
        array_splice($unpackedBoxes, $boxDataIndex, 1);
        $this->unpackedBoxes = $unpackedBoxes;
    }

    /**
     * Return free position data
     *
     * @return array
     */
    private function getFreePositionData()
    {
        $map = $this->getMap();

        $lowest = min($map);
        $index = array_search($lowest, $map);

        $availableWidth = 0;
        $firstFound = false;

        foreach ($map as $item) {
            if ($item == $lowest) {
                $firstFound = true;
                $availableWidth++;
            }
            if ($firstFound && $item > $lowest) {
                break;
            }
        }

        return [
            'lowestPosition' => $index,
            'availableWidth' => $availableWidth,
            'lowest' => $lowest
        ];
    }

    /**
     * Return sprite map
     *
     * @return array
     */
    private function getMap()
    {
        if (empty($this->map)) {
            $this->map = array_fill(0, $this->containerWidth, 0);
        }

        return $this->map;
    }

    /**
     * Search for best fit image and return its index
     *
     * @param $availableWidth
     * @param $x
     *
     * @return int|null|string
     */
    private function getBestFitBox($availableWidth, $x)
    {
        $bestFitIndex = null;
        $bestFitWidth = 0;

        //Allowance for indentation for not first element
        $availableWidth = $x === 0 ?: $availableWidth - $this->spaceBetween;

        foreach ($this->unpackedBoxes as $index => $imageData) {
            $imageWidth = $imageData['width'];
            if ($imageWidth == $availableWidth) {
                return $index;
            } elseif ($imageWidth < $availableWidth && $imageWidth > $bestFitWidth) {
                $bestFitWidth = $imageWidth;
                $bestFitIndex = $index;
            }
        }

        return $bestFitIndex;
    }

    /**
     * Update positions on map as unavailable
     *
     * @param $lowestPosition
     * @param $availableWidth
     */
    private function markCurrentFreeRowAsUnavailable($lowestPosition, $availableWidth)
    {
        $map = $this->getMap();
        $lastPosition = $lowestPosition + $availableWidth;

        for (; $lowestPosition < $lastPosition; $lowestPosition++) {
            $map[$lowestPosition]++;

        }

        $this->map = $map;
    }
}
