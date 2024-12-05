/* From: https://github.com/fnando/sparkline */
/*
MIT License

Copyright (c) 2018 Nando Vieira

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

function getY(max, height, diff, value) {
    return parseFloat((height - (value * height / max) + diff).toFixed(2));
  }

  function removeChildren(svg) {
    [...svg.querySelectorAll("*")].forEach(element => svg.removeChild(element));
  }

  function defaultFetch(entry) {
    return entry.value;
  }

  function buildElement(tag, attrs) {
    const element = document.createElementNS("http://www.w3.org/2000/svg", tag);

    for (let name in attrs) {
      element.setAttribute(name, attrs[name]);
    }

    return element;
  }

  export function sparkline(svg, entries, options) {
    removeChildren(svg);

    if (entries.length <= 1) {
      return;
    }

    options = options || {};

    if (typeof(entries[0]) === "number") {
      entries = entries.map(entry => {
        return {value: entry};
      });
    }

    // This function will be called whenever the mouse moves
    // over the SVG. You can use it to render something like a
    // tooltip.
    const onmousemove = options.onmousemove;

    // This function will be called whenever the mouse leaves
    // the SVG area. You can use it to hide the tooltip.
    const onmouseout = options.onmouseout;

    // Should we run in interactive mode? If yes, this will handle the
    // cursor and spot position when moving the mouse.
    const interactive = ("interactive" in options) ? options.interactive : !!onmousemove;

    // Define how big should be the spot area.
    const spotRadius = options.spotRadius || 2;
    const spotDiameter = spotRadius * 2;

    // Define how wide should be the cursor area.
    const cursorWidth = options.cursorWidth || 2;

    // Get the stroke width; this is used to compute the
    // rendering offset.
    const strokeWidth = parseFloat(svg.attributes["stroke-width"].value);

    // By default, data must be formatted as an array of numbers or
    // an array of objects with the value key (like `[{value: 1}]`).
    // You can set a custom function to return data for a different
    // data structure.
    const fetch = options.fetch || defaultFetch;

    // Retrieve only values, easing the find for the maximum value.
    const values = entries.map(entry => fetch(entry));

    // The rendering width will account for the spot size.
    const width = parseFloat(svg.attributes.width.value) - spotDiameter * 2;

    // Get the SVG element's full height.
    // This is used
    const fullHeight = parseFloat(svg.attributes.height.value);

    // The rendering height accounts for stroke width and spot size.
    const height = fullHeight - (strokeWidth * 2) - spotDiameter;

    // The maximum value. This is used to calculate the Y coord of
    // each sparkline datapoint.
    const max = Math.max(...values);

    // Some arbitrary value to remove the cursor and spot out of
    // the viewing canvas.
    const offscreen = -1000;

    // Cache the last item index.
    const lastItemIndex = values.length - 1;

    // Calculate the X coord base step.
    const offset = width / lastItemIndex;

    // Hold all datapoints, which is whatever we got as the entry plus
    // x/y coords and the index.
    const datapoints = [];

    // Hold the line coordinates.
    const pathY = getY(max, height, strokeWidth + spotRadius, values[0]);
    let pathCoords = `M${spotDiameter} ${pathY}`;

    values.forEach((value, index) => {
      const x = index * offset + spotDiameter;
      const y = getY(max, height, strokeWidth + spotRadius, value);

      datapoints.push(Object.assign({}, entries[index], {
        index: index,
        x: x,
        y: y
      }));

      pathCoords += ` L ${x} ${y}`;
    });

    const path = buildElement("path", {
      class: "sparkline--line",
      d: pathCoords,
      fill: "none"
    });

    let fillCoords = `${pathCoords} V ${fullHeight} L ${spotDiameter} ${fullHeight} Z`;

    const fill = buildElement("path", {
      class: "sparkline--fill",
      d: fillCoords,
      stroke: "none"
    });

    svg.appendChild(fill);
    svg.appendChild(path);

    if (!interactive) {
      return;
    }

    const cursor = buildElement("line", {
      class: "sparkline--cursor",
      x1: offscreen,
      x2: offscreen,
      y1: 0,
      y2: fullHeight,
      "stroke-width": cursorWidth
    });

    const spot = buildElement("circle", {
      class: "sparkline--spot",
      cx: offscreen,
      cy: offscreen,
      r: spotRadius
    });

    svg.appendChild(cursor);
    svg.appendChild(spot);

    const interactionLayer = buildElement("rect", {
      width: svg.attributes.width.value,
      height: svg.attributes.height.value,
      style: "fill: transparent; stroke: transparent",
      class: "sparkline--interaction-layer",
    });
    svg.appendChild(interactionLayer);

    interactionLayer.addEventListener("mouseout", event => {
      cursor.setAttribute("x1", offscreen);
      cursor.setAttribute("x2", offscreen);

      spot.setAttribute("cx", offscreen);

      if (onmouseout) {
        onmouseout(event);
      }
    });

    var realWidth;

    interactionLayer.addEventListener("mouseover", event => {
        realWidth = svg.clientWidth;
    });

    interactionLayer.addEventListener("mousemove", event => {
      const mouseX = event.offsetX;
      // const mouseX = (event.offsetX * width) / realWidth;

      let nextDataPoint = datapoints.find(entry => {
        return entry.x >= mouseX;
      });

      if (!nextDataPoint) {
        nextDataPoint = datapoints[lastItemIndex];
      }

      let previousDataPoint = datapoints[datapoints.indexOf(nextDataPoint) - 1];
      let currentDataPoint;
      let halfway;

      if (previousDataPoint) {
        halfway = previousDataPoint.x + ((nextDataPoint.x - previousDataPoint.x) / 2);
        currentDataPoint = mouseX >= halfway ? nextDataPoint : previousDataPoint;
      } else {
        currentDataPoint = nextDataPoint;
      }

      const x = currentDataPoint.x;
      const y = currentDataPoint.y;

      spot.setAttribute("cx", x);
      spot.setAttribute("cy", y);

      cursor.setAttribute("x1", x);
      cursor.setAttribute("x2", x);

      if (onmousemove) {
        onmousemove(event, currentDataPoint);
      }
    });
  }

  export default sparkline;
