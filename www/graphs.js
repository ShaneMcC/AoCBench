import sparkline from "./ext/sparkline.js";

fetch('./times.json?includeFormat').then(res => res.json()).then(out => handleTimes(out)).catch(err => { throw err });

function handleTimes(times) {
    var elements = document.querySelectorAll('[data-graph]');

    for (const el of elements) {
        var participant = el.getAttribute("data-participant");
        var day = el.getAttribute("data-day");

        el.classList.add('containsSparkline');

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute("width", "200");
        svg.setAttribute("height", "30");
        svg.setAttribute("stroke-width", "2");
        svg.setAttribute("stroke", "blue");
        svg.setAttribute("fill", "rgba(0, 0, 255, .2)");
        el.appendChild(svg);

        var tooltip = document.createElement('span');
        tooltip.setAttribute("hidden", "true");
        tooltip.classList.add("sparklineTooltip");
        el.appendChild(tooltip);

        var thisTimes = times[participant]['days'][day]['times'];
        sparkline(svg, thisTimes, svgoptions);
    }
}

function findClosest(target, tagName) {
    if (target.tagName === tagName) {
        return target;
    }

    while ((target = target.parentNode)) {
        if (target.tagName === tagName) {
            break;
        }
    }

    return target;
}

var svgoptions = {
    onmousemove(event, datapoint) {
        var svg = findClosest(event.target, "svg");
        var tooltip = svg.nextElementSibling;

        tooltip.hidden = false;
        tooltip.textContent = `${datapoint.format}`;
        tooltip.style.top = `${event.pageY}px`;
        tooltip.style.left = `${event.pageX + 20}px`;
    },

    onmouseout() {
        var svg = findClosest(event.target, "svg");
        var tooltip = svg.nextElementSibling;

        tooltip.hidden = true;
    }
};
