from flask import Flask, render_template, request
from pysnmp.hlapi import *
import matplotlib.pyplot as plt
import os
import time

# Flask app
app = Flask(__name__)

# Directory to store graphs
GRAPH_DIR = "static/graphs"
if not os.path.exists(GRAPH_DIR):
    os.makedirs(GRAPH_DIR)

# SNMP Data Fetching Function
def get_snmp_data(target, community, oid, port=161):
    try:
        iterator = getCmd(
            SnmpEngine(),
            CommunityData(community),
            UdpTransportTarget((target, port)),
            ContextData(),
            ObjectType(ObjectIdentity(oid))
        )

        errorIndication, errorStatus, errorIndex, varBinds = next(iterator)

        if errorIndication:
            return None, f"Error: {errorIndication}"
        elif errorStatus:
            return None, f"Error Status: {errorStatus.prettyPrint()}"
        else:
            for varBind in varBinds:
                return float(varBind[1]), None  # Return the SNMP value as float
    except Exception as e:
        return None, f"Exception: {e}"

# Function to generate graphs
def generate_graph(data, labels, output_file):
    plt.figure(figsize=(10, 5))
    plt.plot(labels, data, marker="o", color="b")
    plt.title("SNMP Data Over Time")
    plt.xlabel("Time")
    plt.ylabel("Value")
    plt.grid()
    plt.savefig(output_file)
    plt.close()

# Flask Routes
@app.route("/", methods=["GET", "POST"])
def index():
    if request.method == "POST":
        target = request.form["target"]
        community = request.form["community"]
        oid = request.form["oid"]
        interval = int(request.form["interval"])
        duration = int(request.form["duration"])

        labels = []
        data = []

        for i in range(duration):
            value, error = get_snmp_data(target, community, oid)
            if error:
                return f"Error fetching SNMP data: {error}"

            data.append(value)
            labels.append(time.strftime("%H:%M:%S"))
            time.sleep(interval)

        # Generate graph
        graph_file = os.path.join(GRAPH_DIR, f"graph_{int(time.time())}.png")
        generate_graph(data, labels, graph_file)

        return render_template("index.html", graph=graph_file)

    return render_template("index.html", graph=None)

# HTML Template
HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNMP Monitoring</title>
</head>
<body>
    <h1>SNMP Monitoring Tool</h1>
    <form method="POST">
        <label for="target">Target Device:</label>
        <input type="text" id="target" name="target" required><br><br>
        <label for="community">SNMP Community:</label>
        <input type="text" id="community" name="community" value="public" required><br><br>
        <label for="oid">OID:</label>
        <input type="text" id="oid" name="oid" required><br><br>
        <label for="interval">Polling Interval (seconds):</label>
        <input type="number" id="interval" name="interval" value="5" required><br><br>
        <label for="duration">Duration (polls):</label>
        <input type="number" id="duration" name="duration" value="12" required><br><br>
        <button type="submit">Start Monitoring</button>
    </form>
    {% if graph %}
        <h2>SNMP Data Graph</h2>
        <img src="{{ graph }}" alt="Graph">
    {% endif %}
</body>
</html>
"""

# Write the HTML template to a file
with open("templates/index.html", "w") as f:
    f.write(HTML_TEMPLATE)

if __name__ == "__main__":
    app.run(debug=True)